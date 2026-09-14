<?php

namespace App\Tests\Service;

use App\Entity\Activity;
use App\Repository\ActivityRepository;
use App\Repository\ScanRepository;
use App\Repository\SphereRepository;
use App\Service\AppParameterService;
use App\Service\MapService;
use App\Tests\Support\EntityBuilder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

class MapServiceTest extends TestCase
{
    private function service(
        ?CacheInterface $cache = null,
        ?ScanRepository $scanRepository = null,
        ?AppParameterService $params = null,
    ): MapService {
        return new MapService(
            $this->createStub(SphereRepository::class),
            $this->createStub(ActivityRepository::class),
            $scanRepository ?? $this->createStub(ScanRepository::class),
            $params ?? $this->createStub(AppParameterService::class),
            $this->createStub(EntityManagerInterface::class),
            $cache ?? $this->createStub(CacheInterface::class),
        );
    }

    private function stand(int $hardLimit = 0, int $softLimit = 0): Activity
    {
        return EntityBuilder::activity(1, EntityBuilder::category())
            ->setHardLimit($hardLimit)
            ->setSoftLimit($softLimit);
    }

    /** @param array<int, int> $occupancy */
    private function capacity(Activity $activity, array $occupancy, int $marginPct = 80): string
    {
        $scanRepository = $this->createStub(ScanRepository::class);
        $scanRepository->method('countRecentGroupedByActivity')->willReturn($occupancy);

        $params = $this->createStub(AppParameterService::class);
        $params->method('getInt')->willReturn($marginPct);

        return $this->service(null, $scanRepository, $params)->activityToArray($activity)['capacity'];
    }

    public function testUnStandSansLimiteResteToujoursOuvert(): void
    {
        $this->assertSame('ok', $this->capacity($this->stand(), [1 => 999]));
    }

    public function testUnStandAtteignantSaLimiteDureEstPlein(): void
    {
        $this->assertSame('full', $this->capacity($this->stand(10), [1 => 10]));
    }

    public function testLaMargeDeCapaciteRendUnStandPresquePlein(): void
    {
        $this->assertSame('almost', $this->capacity($this->stand(10), [1 => 8]));
        $this->assertSame('ok', $this->capacity($this->stand(10), [1 => 7]));
    }

    public function testUneLimiteSoupleExpliciteRemplaceLaMarge(): void
    {
        $this->assertSame('almost', $this->capacity($this->stand(10, 3), [1 => 3]));
    }

    public function testUnStandSansScanRecentEstOuvert(): void
    {
        $this->assertSame('ok', $this->capacity($this->stand(10), []));
    }

    public function testLesActivitesSansCoordonneesSontCentreesSurLaCarte(): void
    {
        $data = $this->service()->activityToArray($this->stand());

        $this->assertSame(50.0, $data['pointXActivity']);
        $this->assertSame(50.0, $data['pointYActivity']);
        $this->assertSame('', $data['descriptionActivity']);
    }

    public function testLInvalidationViseLaCleDeLaCarte(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('delete')->with('map.spheres');

        $this->service($cache)->invalidateCache();
    }
}
