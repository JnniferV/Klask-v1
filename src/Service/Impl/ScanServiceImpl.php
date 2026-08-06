<?php

namespace App\Service\Impl;

use App\Entity\Activity;
use App\Entity\Scan;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\ParcoursRepository;
use App\Repository\ScanRepository;
use App\Repository\UserSphereRatingRepository;
use App\Service\AppParameterService;
use App\Service\ParcoursService;
use App\Service\RealtimeNotifier;
use App\Service\ScanService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias]
class ScanServiceImpl implements ScanService
{
    //hors des 3 préférences de l'élève, points des sphères
    private const POINTS_OUTSIDE_TOP3 = 25;

    public function __construct(
        private readonly ActivityRepository         $activityRepository,
        private readonly ScanRepository             $scanRepository,
        private readonly ParcoursRepository         $parcoursRepository,
        private readonly UserSphereRatingRepository $userSphereRatingRepository,
        private readonly EntityManagerInterface     $em,
        private readonly ParcoursService            $parcoursService,
        private readonly AppParameterService        $params,
        private readonly RealtimeNotifier           $notifier,
    ) {}

    public function process(User $user, string $token): array
    {
        $blockedUntil = $user->getBlockedUntil();
        if ($blockedUntil !== null && $blockedUntil > new DateTimeImmutable()) {
            return $this->fail('Compte temporairement bloqué.', null, $user);
        }

        $activity = $this->activityRepository->findOneByQrcodeToken($token);

        if ($activity === null) {
            $user->setInvalidScanCount($user->getInvalidScanCount() + 1);
            if ($user->getInvalidScanCount() >= $this->params->getInt('INVALID_SCAN_THRESHOLD', 3)) {
                $user->setBlockedUntil(new DateTimeImmutable('+' . $this->params->getInt('BLOCK_DURATION_MINUTES', 5) . ' minutes'));
                $user->setInvalidScanCount(0);
            }
            $this->em->flush();

            return $this->fail('QR invalide.', null, $user);
        }

        if (!$activity->isAvailable()) {
            return $this->fail('Stand fermé.', $activity, $user);
        }

        if ($this->scanRepository->existsForUserAndActivity($user, $activity)) {
            return $this->fail('Déjà scanné.', $activity, $user);
        }

        $hardLimit = $activity->getHardLimit();
        if ($hardLimit > 0 && $this->scanRepository->countRecentForActivity($activity) >= $hardLimit) {
            return $this->fail('Stand complet — repasse dans quelques minutes.', $activity, $user);
        }

        if (!$activity->isInternship()) {
            $delayMin = $this->params->getInt('SCAN_DELAY_MINUTES', 5);
            $lastAt   = $this->scanRepository->findLastAt($user);
            if ($lastAt !== null && $lastAt > new DateTimeImmutable("-{$delayMin} minutes")) {
                return $this->fail('Attends encore un peu avant le prochain scan.', $activity, $user);
            }
        }

        $points = $this->resolvePoints($user, $activity);
        $bonus  = $this->resolveCompletionBonus($user, $activity);
        $group  = $user->getGroup();

        $user->setScore(($user->getScore() ?? 0) + $points + $bonus);
        $user->setInvalidScanCount(0);
        $group?->setScore(($group->getScore() ?? 0) + $points + $bonus);

        $this->em->persist(new Scan(new DateTimeImmutable(), $activity, $user));
        $this->em->flush();

        $this->parcoursService->invalidatePath($user);

        // Sidebar accompagnateur
        if ($group !== null) {
            $this->notifier->publish('group-score/' . $group->getCode(), [
                'studentId'    => $user->getId(),
                'studentScore' => $user->getScore(),
            ]);
        }

        return [
            'ok'           => true,
            'points'       => $points,
            'bonus'        => $bonus,
            'activityName' => $activity->getName(),
            'activityId'   => $activity->getId(),
            'userScore'    => $user->getScore(),
            'groupScore'   => $group?->getScore() ?? 0,
            'error'        => null,
        ];
    }


    private function resolvePoints(User $user, Activity $activity): int
    {
        $category = $activity->getCategory();

        if (!$category->isStand() || $activity->getSphere() === null) {
            return $category->getNbrPoints();
        }

        $topIds = array_column(
            array_slice($this->userSphereRatingRepository->findRatingsOrderedByScore($user), 0, 3),
            'sphereId'
        );

        return empty($topIds) || in_array($activity->getSphere()->getId(), $topIds, true)
            ? $category->getNbrPoints()
            : self::POINTS_OUTSIDE_TOP3;
    }

    private function resolveCompletionBonus(User $user, Activity $activity): int
    {
        $currentId = (int) $activity->getId();
        $scanned   = $this->scanRepository->findActivityIdsByUser($user);
        $scanned[] = $currentId; // le scan courant

        $completes = static fn(array $ids): bool => in_array($currentId, $ids, true) && !array_diff($ids, $scanned);

        $isStand = $this->activityRepository->findMapIdsByStandFlag();
        // les 3 sphères préférées de l'élève
        $steps   = array_column($this->parcoursRepository->findOrderedByUser($user), 'activityId');

        return ($completes(array_map('intval', array_slice($steps, 0, 3))) ? $this->params->getInt('BONUS_TOP3_SPHERES', 50) : 0)
            + ($completes(array_keys(array_filter($isStand))) ? $this->params->getInt('BONUS_ALL_SPHERES', 100) : 0)
            + ($completes(array_keys($isStand)) ? $this->params->getInt('BONUS_MAX_SCORE', 150) : 0);
    }

    private function fail(string $error, ?Activity $activity, User $user): array
    {
        return [
            'ok'           => false,
            'error'        => $error,
            'points'       => 0,
            'bonus'        => 0,
            'activityName' => $activity?->getName() ?? '',
            'activityId'   => $activity?->getId() ?? 0,
            'userScore'    => $user->getScore() ?? 0,
            'groupScore'   => $user->getGroup()?->getScore() ?? 0,
        ];
    }
}
