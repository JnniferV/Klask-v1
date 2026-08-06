<?php

namespace App\Tests\Service;

use App\Entity\Activity;
use App\Repository\ActivityRepository;
use App\Repository\ScanRepository;
use App\Repository\SphereRepository;
use App\Service\AppParameterService;
use App\Service\Impl\MapServiceImpl;
use App\Tests\Support\EntityBuilder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

class MapServiceImplTest extends TestCase
{
    private function service(?CacheInterface $cache = null): MapServiceImpl
    {
        return new MapServiceImpl(
            $this->createMock(SphereRepository::class),
            $this->createMock(ActivityRepository::class),
            $this->createMock(ScanRepository::class),
            $this->createMock(AppParameterService::class),
            $this->createMock(EntityManagerInterface::class),
            $cache ?? $this->createMock(CacheInterface::class),
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
        return $this->service()->activityToArray($activity, $occupancy, $marginPct)['capacity'];
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
