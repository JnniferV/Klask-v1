<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Parcours;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\ParcoursRepository;
use App\Repository\ScanRepository;
use App\Repository\UserSphereRatingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ParcoursService
{
    private const CACHE_TTL = 30;

    public function __construct(
        private readonly ParcoursRepository $parcoursRepository,
        private readonly UserSphereRatingRepository $userSphereRatingRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly ScanRepository $scanRepository,
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache,
        private readonly AppParameterService $params,
    ) {
    }

    /** @param list<array{sphereId: int, rating: int}> $preloadedRatings */
    public function generateForUser(User $user, array $preloadedRatings = []): void
    {
        $this->parcoursRepository->deleteByUser($user);

        $source = $preloadedRatings ?: $this->userSphereRatingRepository->findRatingsOrderedByScore($user);
        if (!$source) {
            $this->em->flush();

            return;
        }

        $sphereIds = array_column($source, 'sphereId');
        $ratingBySphere = array_column($source, 'rating', 'sphereId');

        $top3Count = min(3, count($sphereIds));
        $top3 = array_slice($sphereIds, 0, $top3Count);
        $bonus = array_slice($sphereIds, $top3Count);
        $offset = $user->getId() % $top3Count;
        $top3 = array_merge(array_slice($top3, $offset), array_slice($top3, 0, $offset));

        // l'équilibrage se fait maintenant à l'affichage, sur la charge réelle
        $orderedIds = array_merge($top3, $bonus);

        $grouped = $this->activityRepository->findStandsBySpheres($orderedIds);

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

    /** @return array{steps: array<int, array{step: int, id: int, current: bool, done: bool, urgent?: bool}>, scannedIds: int[]} */
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
        return 'parcours.map.'.$user->getId();
    }

    /** @return array{steps: array<int, array{step: int, id: int, current: bool, done: bool, urgent?: bool}>, scannedIds: int[]} */
    private function buildPathForMap(User $user): array
    {
        $scannedIds = $this->scanRepository->findActivityIdsByUser($user);

        $rows = $this->parcoursRepository->findOrderedByUser($user);
        if (!$rows) {
            return ['steps' => [], 'scannedIds' => $scannedIds];
        }

        $doneIds = array_flip($scannedIds);
        $result = [];

        foreach ($rows as $row) {
            $id = (int) $row['activityId'];
            $result[] = ['step' => (int) $row['stepOrder'], 'id' => $id, 'current' => false, 'done' => isset($doneIds[$id])];
        }

        $currentIdx = $this->pickCurrentIndex($rows, $result);
        if (null !== $currentIdx) {
            $result[$currentIdx]['current'] = true;

            // l'atelier imminent se glisse juste après l'étape en cours
            $urgent = $this->findUrgentActivity($doneIds);
            if (null !== $urgent) {
                array_splice($result, $currentIdx + 1, 0, [[
                    'step' => 0,
                    'id' => (int) $urgent->getId(),
                    'current' => false,
                    'done' => false,
                    'urgent' => true,
                ]]);
            }
        }

        return ['steps' => $result, 'scannedIds' => $scannedIds];
    }

    /**
     * @param array<int, array{activityId: int, stepOrder: int, isAvailable: string, priority: int, sphereId: ?int}> $rows
     * @param array<int, array{step: int, id: int, current: bool, done: bool}>                                       $result
     */
    private function pickCurrentIndex(array $rows, array $result): ?int
    {
        $libres = [];
        foreach ($rows as $i => $row) {
            if (!$result[$i]['done'] && (bool) (int) $row['isAvailable']) {
                $libres[$i] = $row;
            }
        }

        // plus rien de libre : on garde la première étape non faite
        if (!$libres) {
            foreach ($result as $i => $entry) {
                if (!$entry['done']) {
                    return $i;
                }
            }

            return null;
        }

        // tant qu'il reste des stands des 3 sphères préférées, on n'en sort pas
        $pool = array_filter($libres, static fn (array $r): bool => $r['priority'] >= 1 && $r['priority'] <= 3) ?: $libres;

        $charge = $this->chargeParSphere();
        $max = $this->params->getInt('MAX_STUDENTS_PER_SPHERE', 100);

        // première étape dont la sphère a encore de la place
        foreach ($pool as $i => $row) {
            if (($charge[(int) $row['sphereId']] ?? 0) < $max) {
                return $i;
            }
        }

        // tout est saturé : on n'impose rien, on vise la sphère la moins chargée
        $best = null;
        foreach ($pool as $i => $row) {
            if (null === $best || ($charge[(int) $row['sphereId']] ?? 0) < ($charge[(int) $pool[$best]['sphereId']] ?? 0)) {
                $best = $i;
            }
        }

        return $best;
    }

    // une seule requête pour tout le monde, rafraîchie comme les parcours
    /** @return array<int, int> */
    private function chargeParSphere(): array
    {
        return $this->cache->get('parcours.charge.spheres', function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->scanRepository->countRecentGroupedBySphere();
        });
    }

    /** @param array<int, int> $doneIds */
    private function findUrgentActivity(array $doneIds): ?Activity
    {
        $now = new \DateTimeImmutable();
        $alertMin = $this->params->getInt('ALERT_BEFORE_EVENT_MIN', 10);

        foreach ($this->activityRepository->findScheduledActivities() as $activity) {
            if (isset($doneIds[$activity->getId()])) {
                continue;
            }
            $start = $activity->getCategory()?->getBeginningHourCategory();
            if (null === $start) {
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
