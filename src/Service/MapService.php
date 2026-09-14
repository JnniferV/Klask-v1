<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Sphere;
use App\Repository\ActivityRepository;
use App\Repository\ScanRepository;
use App\Repository\SphereRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class MapService
{
    private const CACHE_KEY = 'map.spheres';
    private const CACHE_TTL = 300;
    private const SOFT_MARGIN_DEFAULT = 80;
    private const COLOR_UNCLASSIFIED = '#6c5ce7';

    public function __construct(
        private readonly SphereRepository $sphereRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly ScanRepository $scanRepository,
        private readonly AppParameterService $params,
        private readonly EntityManagerInterface $em,
        private readonly CacheInterface $cache,
    ) {
    }

    public function getPreparedSpheresJson(): string
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): string {
            $item->expiresAfter(self::CACHE_TTL);

            return json_encode($this->buildSpheres(), \JSON_THROW_ON_ERROR);
        });
    }

    public function invalidateCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }

    /** @return array{spheres: list<array<string, mixed>>, standalone: array<int, array<string, mixed>>} */
    private function buildSpheres(): array
    {
        $spheres = $this->sphereRepository->findAllWithStands();
        $result = [];

        $occupancy = $this->scanRepository->countRecentGroupedByActivity();
        $marginPct = $this->params->getInt('SOFT_CAPACITY_MARGIN', self::SOFT_MARGIN_DEFAULT);

        foreach ($spheres as $sphere) {
            $activityData = [];

            foreach ($sphere->getActivities() as $activity) {
                $activityData[] = $this->toArray($activity, $occupancy, $marginPct);
            }

            $bounds = SphereBoundsCalculator::fromActivities($activityData);

            $result[] = [
                'id' => $sphere->getId(),
                'name' => $sphere->getName(),
                'color' => $sphere->getColor(),
                'centerX' => $sphere->getPointX() ?? $bounds['centerX'] ?? 50.0,
                'centerY' => $sphere->getPointY() ?? $bounds['centerY'] ?? 50.0,
                'radius' => $sphere->getRadius(),
                'activities' => $activityData,
            ];
        }

        $standalone = array_map(
            fn (Activity $a) => $this->toArray($a, $occupancy, $marginPct),
            $this->activityRepository->findStandaloneActivities()
        );

        return ['spheres' => $result, 'standalone' => $standalone];
    }

    // occupancy requise
    /** @return array<string, mixed> */
    public function activityToArray(Activity $activity): array
    {
        return $this->toArray(
            $activity,
            $this->scanRepository->countRecentGroupedByActivity(),
            $this->params->getInt('SOFT_CAPACITY_MARGIN', self::SOFT_MARGIN_DEFAULT),
        );
    }

    /**
     * @param array<int, int> $occupancy
     *
     * @return array{id: ?int, name: string, pointXActivity: float, pointYActivity: float, descriptionActivity: string, isAvailable: bool, isInternship: bool, waitMinutes: ?int, capacity: string, sphereId: ?int, color: string, categoryType: string, basePoints: int}
     */
    private function toArray(Activity $activity, array $occupancy, int $marginPct): array
    {
        return [
            'id' => $activity->getId(),
            'name' => $activity->getName(),
            'pointXActivity' => $activity->getPointX() ?? 50.0,
            'pointYActivity' => $activity->getPointY() ?? 50.0,
            'descriptionActivity' => $activity->getDescription() ?? '',
            'isAvailable' => $activity->isAvailable(),
            'isInternship' => $activity->isInternship(),
            'waitMinutes' => $activity->getEstimatedWaitMinutes(),
            'capacity' => $this->capacityStatus($activity, $occupancy[$activity->getId()] ?? 0, $marginPct),
            'sphereId' => $activity->getSphere()?->getId(),
            'color' => $activity->getSphere()?->getColor() ?? self::COLOR_UNCLASSIFIED,
            'categoryType' => $activity->getCategory()?->getType() ?? '',
            'basePoints' => $activity->getCategory()?->getNbrPoints() ?? 0,
        ];
    }

    private function capacityStatus(Activity $activity, int $recentScans, int $marginPct): string
    {
        $hard = $activity->getHardLimit();
        if ($hard <= 0) {
            return 'ok';
        }
        if ($recentScans >= $hard) {
            return 'full';
        }
        $soft = $activity->getSoftLimit() ?: (int) ceil($hard * $marginPct / 100);

        return $recentScans >= $soft ? 'almost' : 'ok';
    }

    public function savePosition(Sphere|Activity $entity, float $x, float $y, ?float $radius = null): void
    {
        $entity->setPointX($x);
        $entity->setPointY($y);
        if ($entity instanceof Sphere && null !== $radius) {
            $entity->setRadius($radius);
        }
        $this->em->flush();
    }
}
