<?php

namespace App\Service;

use App\Dto\ScanResult;
use App\Entity\Activity;
use App\Entity\Scan;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\GroupRepository;
use App\Repository\ParcoursRepository;
use App\Repository\ScanRepository;
use App\Security\RoleSecurity;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class ScanService
{
    public const POINTS_OUTSIDE_TOP3 = 25;

    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly ScanRepository $scanRepository,
        private readonly ParcoursRepository $parcoursRepository,
        private readonly EntityManagerInterface $em,
        private readonly ParcoursService $parcoursService,
        private readonly UserService $userService,
        private readonly AppParameterService $params,
        private readonly RealtimeNotifier $notifier,
        private readonly GroupRepository $groupRepository,
    ) {
    }

    public function process(User $user, string $token): ScanResult
    {
        // route publique
        if ($user->getAuthority()?->getAuthorityUser() !== RoleSecurity::STUDENT->value) {
            return $this->fail('Seuls les élèves peuvent valider un stand.', null, $user);
        }

        if ($user->isBlocked()) {
            return $this->fail('Compte temporairement bloqué.', null, $user);
        }

        $activity = $this->activityRepository->findOneByQrcodeToken($token);

        if (null === $activity) {
            return $this->registerViolation('QR invalide.', null, $user, 'invalid_qr');
        }

        if (!$activity->isAvailable()) {
            return $this->fail('Stand fermé.', $activity, $user);
        }

        if ($this->scanRepository->existsForUserAndActivity($user, $activity)) {
            return $this->fail('Déjà scanné.', $activity, $user, 'already_scanned');
        }

        $hardLimit = $activity->getHardLimit();
        if ($hardLimit > 0 && $this->scanRepository->countRecentForActivity($activity) >= $hardLimit) {
            return $this->fail('Stand complet — repasse dans quelques minutes.', $activity, $user);
        }

        if (!$activity->isInternship()) {
            $delayMin = $this->params->getInt('SCAN_DELAY_MINUTES', 5);
            $lastAt = $this->scanRepository->findLastAt($user);
            if (null !== $lastAt && $lastAt > new \DateTimeImmutable("-{$delayMin} minutes")) {
                return $this->registerViolation('Attends encore un peu avant le prochain scan.', $activity, $user, 'fast_scan');
            }
        }

        $points = $this->resolvePoints($user, $activity);
        $bonus = $this->resolveCompletionBonus($user, $activity);
        $group = $user->getGroup();

        // scan avant crédit
        $this->em->persist(new Scan(new \DateTimeImmutable(), $activity, $user));

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // race concurrente
            return $this->fail('Déjà scanné.', $activity, $user, 'already_scanned');
        }

        $user->creditPoints($points + $bonus);
        $this->em->flush();

        $this->parcoursService->invalidatePath($user);

        if (null !== $group) {
            $this->groupRepository->addScore($group, $points + $bonus);

            $this->notifier->publish('group-score/'.$group->getCode(), [
                'studentId' => $user->getId(),
                'studentScore' => $user->getScore(),
            ]);
            $this->notifier->publish('group-total/'.$group->getCode(), ['groupScore' => $group->getScore()]);
        }

        if (BilanService::MIN_SCANS_FOR_PDF === $this->scanRepository->countByUser($user)) {
            $this->notifier->publish('event-alert/user/'.$user->getPseudo(), [
                'alert' => true,
                'title' => 'Bilan débloqué !',
                'message' => 'Tu peux télécharger ton bilan PDF.',
                'type' => 'success',
            ]);
        }

        return ScanResult::valide(
            $points,
            $bonus,
            (string) $activity->getName(),
            (int) $activity->getId(),
            $user->getScore() ?? 0,
            $group?->getScore() ?? 0,
        );
    }

    private function resolvePoints(User $user, Activity $activity): int
    {
        $category = $activity->getCategory();
        if (null === $category) {
            return 0;
        }

        if (!$category->isStand() || null === $activity->getSphere()) {
            return $category->getNbrPoints();
        }

        $topIds = $this->userService->getTopAndBottomSphereIds($user)['top'];

        return empty($topIds) || in_array($activity->getSphere()->getId(), $topIds, true)
            ? $category->getNbrPoints()
            : self::POINTS_OUTSIDE_TOP3;
    }

    private function resolveCompletionBonus(User $user, Activity $activity): int
    {
        $currentId = (int) $activity->getId();
        $scanned = $this->scanRepository->findActivityIdsByUser($user);
        $scanned[] = $currentId;

        $completes = static fn (array $ids): bool => in_array($currentId, $ids, true) && !array_diff($ids, $scanned);

        $isStand = $this->activityRepository->findMapIdsByStandFlag();
        $top3 = array_column(
            array_filter(
                $this->parcoursRepository->findOrderedByUser($user),
                static fn (array $row): bool => $row['priority'] >= 1 && $row['priority'] <= 3,
            ),
            'activityId'
        );

        return ($completes(array_map('intval', $top3)) ? $this->params->getInt('BONUS_TOP3_SPHERES', 50) : 0)
            + ($completes(array_keys(array_filter($isStand))) ? $this->params->getInt('BONUS_ALL_SPHERES', 100) : 0)
            + ($completes(array_keys($isStand)) ? $this->params->getInt('BONUS_MAX_SCORE', 150) : 0);
    }

    private function registerViolation(string $error, ?Activity $activity, User $user, string $reason): ScanResult
    {
        $blocked = $user->registerInvalidScan(
            $this->params->getInt('INVALID_SCAN_THRESHOLD', 3),
            $this->params->getInt('BLOCK_DURATION_MINUTES', 5),
        );
        $this->em->flush();

        if (($blocked || 'fast_scan' === $reason) && ($group = $user->getGroup()) !== null) {
            $this->notifier->publish('group-score/'.$group->getCode(), [
                'studentId' => $user->getId(),
                'pseudo' => $user->getPseudo() ?? '',
                'reason' => $reason,
                'blocked' => $blocked,
            ]);
        }

        return $this->fail($error, $activity, $user);
    }

    private function fail(string $error, ?Activity $activity, User $user, ?string $code = null): ScanResult
    {
        return ScanResult::refus(
            $error,
            $code,
            $activity?->getName() ?? '',
            $activity?->getId() ?? 0,
            $user->getScore() ?? 0,
            $user->getGroup()?->getScore() ?? 0,
        );
    }
}
