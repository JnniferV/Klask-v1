<?php

namespace App\Service\Impl;

use App\Entity\Activity;
use App\Entity\Parcours;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\ParcoursRepository;
use App\Repository\ScanRepository;
use App\Repository\UserSphereRatingRepository;
use App\Service\AppParameterService;
use App\Service\ParcoursService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AsAlias]
class ParcoursServiceImpl implements ParcoursService
{
    private const CACHE_TTL = 30;
    private const CROWD_THRESHOLD = 30;

    public function __construct(
        private readonly ParcoursRepository         $parcoursRepository,
        private readonly UserSphereRatingRepository $userSphereRatingRepository,
        private readonly ActivityRepository         $activityRepository,
        private readonly ScanRepository             $scanRepository,
        private readonly EntityManagerInterface     $em,
        private readonly CacheInterface             $cache,
        private readonly AppParameterService        $params,
    ) {}

    // Génère le parcours complet en 6 étapes

    public function generateForUser(User $user, array $preloadedRatings = []): void
    {
        $this->parcoursRepository->deleteByUser($user);

        $source = $preloadedRatings ?: $this->userSphereRatingRepository->findRatingsOrderedByScore($user);
        if (!$source) {
            $this->em->flush();
            return;
        }

        $sphereIds      = array_column($source, 'sphereId');
        $ratingBySphere = array_column($source, 'rating', 'sphereId');

        // Top 3 préférées
        $top3Count = min(3, count($sphereIds));
        $top3      = array_slice($sphereIds, 0, $top3Count);
        $bonus     = array_slice($sphereIds, $top3Count);
        $offset    = $user->getId() % $top3Count;
        $top3      = array_merge(array_slice($top3, $offset), array_slice($top3, 0, $offset));

        // attente, pour démarrer par la sphère la moins chargée
        $loadAtStep1 = $this->parcoursRepository->countStudentsPerSphereAtStep($top3, 1);
        if (($loadAtStep1[$top3[0]] ?? 0) >= self::CROWD_THRESHOLD) {
            usort($top3, fn(int $a, int $b) => ($loadAtStep1[$a] ?? 0) <=> ($loadAtStep1[$b] ?? 0));
        }

        $orderedIds = array_merge($top3, $bonus);

        $grouped = $this->activityRepository->findStandsBySpheres($orderedIds);

        // xxclu les activités déjà scannées
        $doneIds = array_flip($this->scanRepository->findActivityIdsByUser($user));

        $step = 1;
        foreach ($orderedIds as $sphereId) {
            foreach ($grouped[$sphereId] ?? [] as $activity) {
                if (isset($doneIds[$activity->getId()])) {
                    continue;
                }
                $this->em->persist(new Parcours(
                    $user,
                    $activity,
                    $ratingBySphere[$sphereId] ?? 0,
                    $step++,
                ));
            }
        }

        $this->em->flush();
    }

    /**
     * retourne le chemin pour la carte
     * @return array{steps: array<int, array{step: int, id: int, current: bool, done: bool, urgent?: bool}>, scannedIds: int[]}
     */
    public function getPathForMap(User $user): array
    {
        return $this->cache->get($this->cacheKey($user), function (ItemInterface $item) use ($user): array {
            $item->expiresAfter(self::CACHE_TTL);
            return $this->buildPathForMap($user);
        });
    }

    public function invalidatePath(User $user): void
    {
        $this->cache->delete($this->cacheKey($user));
    }

    private function cacheKey(User $user): string
    {
        return 'parcours.map.' . $user->getId();
    }

    private function buildPathForMap(User $user): array
    {
        $scannedIds = $this->scanRepository->findActivityIdsByUser($user);

        $rows = $this->parcoursRepository->findOrderedByUser($user);
        if (!$rows) {
            return ['steps' => [], 'scannedIds' => $scannedIds];
        }

        $doneIds      = array_flip($scannedIds);
        $currentFound = false;
        $result       = [];

        foreach ($rows as $row) {
            $id        = (int) $row['activityId'];
            $done      = isset($doneIds[$id]);
            $available = (bool)(int)$row['isAvailable'];

            $current   = !$done && !$currentFound && $available;
            if ($current) {
                $currentFound = true;
            }
            $result[] = ['step' => (int) $row['stepOrder'], 'id' => $id, 'current' => $current, 'done' => $done];
        }

        if (!$currentFound) {
            foreach ($result as &$entry) {
                if (!$entry['done']) {
                    $entry['current'] = true;
                    break;
                }
            }
            unset($entry);
        }

        // atelier/conférence urgent (car flexible et programmé avec des horaires)
        $urgent = $this->findUrgentActivity($doneIds);
        if ($urgent !== null) {
            $currentIdx = array_search(true, array_column($result, 'current'));
            if ($currentIdx !== false) {
                array_splice($result, $currentIdx + 1, 0, [[
                    'step'    => 0,
                    'id'      => $urgent->getId(),
                    'current' => false,
                    'done'    => false,
                    'urgent'  => true,
                ]]);
            }
        }

        return ['steps' => $result, 'scannedIds' => $scannedIds];
    }

    /**
     * retourne première activité atelier/Conf démarrant dans 10min
     * @param array<int, int> $doneIds clés = IDs d'activités déjà scannées
     */
    private function findUrgentActivity(array $doneIds): ?Activity
    {
        $now      = new \DateTimeImmutable();
        $alertMin = $this->params->getInt('ALERT_BEFORE_EVENT_MIN', 10);

        foreach ($this->activityRepository->findScheduledActivities() as $activity) {
            if (isset($doneIds[$activity->getId()])) {
                continue;
            }
            $start = $activity->getCategory()->getBeginningHourCategory();
            if ($start === null) {
                continue;
            }
            $diffMin = ($start->getTimestamp() - $now->getTimestamp()) / 60;
            if ($diffMin >= 0 && $diffMin <= $alertMin) {
                return $activity;
            }
        }

        return null;
    }
}
