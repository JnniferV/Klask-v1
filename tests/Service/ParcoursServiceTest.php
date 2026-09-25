<?php

namespace App\Tests\Service;

use App\Entity\ActivityCategory;
use App\Entity\Parcours;
use App\Repository\ActivityRepository;
use App\Repository\ParcoursRepository;
use App\Repository\ScanRepository;
use App\Repository\UserSphereRatingRepository;
use App\Service\AppParameterService;
use App\Service\ParcoursService;
use App\Tests\Support\EntityBuilder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ParcoursServiceTest extends TestCase
{
    private ParcoursRepository&Stub $parcoursRepository;
    private UserSphereRatingRepository&Stub $ratingRepository;
    private ActivityRepository&Stub $activityRepository;
    private ScanRepository&Stub $scanRepository;
    /** @var Parcours[] */
    private array $persisted = [];

    protected function setUp(): void
    {
        $this->parcoursRepository = $this->createStub(ParcoursRepository::class);
        $this->ratingRepository = $this->createStub(UserSphereRatingRepository::class);
        $this->activityRepository = $this->createStub(ActivityRepository::class);
        $this->scanRepository = $this->createStub(ScanRepository::class);
        $this->persisted = [];

        $this->scanRepository->method('findActivityIdsByUser')->willReturn([]);
        $this->activityRepository->method('findScheduledActivities')->willReturn([]);
    }

    private function service(): ParcoursService
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            if ($entity instanceof Parcours) {
                $this->persisted[] = $entity;
            }
        });

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(
            fn (string $key, callable $callback) => $callback($this->createStub(ItemInterface::class))
        );

        $params = $this->createStub(AppParameterService::class);
        $params->method('getInt')->willReturnCallback(static fn (string $key, int $default = 0) => $default);

        return new ParcoursService(
            $this->parcoursRepository,
            $this->ratingRepository,
            $this->activityRepository,
            $this->scanRepository,
            $em,
            $cache,
            $params,
        );
    }

    public function testGenereUneEtapeParStandDansLOrdreDesPreferences(): void
    {
        $category = EntityBuilder::category(ActivityCategory::TYPE_STAND);
        $this->activityRepository->method('findStandsBySpheres')->willReturn([
            10 => [EntityBuilder::activity(1, $category, EntityBuilder::sphere(10))],
            20 => [EntityBuilder::activity(2, $category, EntityBuilder::sphere(20))],
            30 => [EntityBuilder::activity(3, $category, EntityBuilder::sphere(30))],
        ]);

        $this->service()->generateForUser(EntityBuilder::student(3), [
            ['sphereId' => 10, 'rating' => 6],
            ['sphereId' => 20, 'rating' => 5],
            ['sphereId' => 30, 'rating' => 4],
        ]);

        $this->assertCount(3, $this->persisted);
        $this->assertSame([1, 2, 3], array_map(fn (Parcours $p) => $p->getActivity()->getId(), $this->persisted));
        $this->assertSame([1, 2, 3], array_map(fn (Parcours $p) => $p->getStepOrder(), $this->persisted));
    }

    public function testLaPrioriteDeChaqueEtapeReprendLaNoteDeLaSphere(): void
    {
        $category = EntityBuilder::category();
        $this->activityRepository->method('findStandsBySpheres')->willReturn([
            10 => [EntityBuilder::activity(1, $category, EntityBuilder::sphere(10))],
        ]);

        $this->service()->generateForUser(EntityBuilder::student(3), [['sphereId' => 10, 'rating' => 6]]);

        $this->assertSame(6, $this->persisted[0]->getPriority());
    }

    public function testLesActivitesDejaScanneesSontExcluesDuParcours(): void
    {
        $category = EntityBuilder::category();
        $this->activityRepository->method('findStandsBySpheres')->willReturn([
            10 => [EntityBuilder::activity(1, $category, EntityBuilder::sphere(10))],
            20 => [EntityBuilder::activity(2, $category, EntityBuilder::sphere(20))],
        ]);

        $scanRepository = $this->createStub(ScanRepository::class);
        $scanRepository->method('findActivityIdsByUser')->willReturn([1]);
        $this->scanRepository = $scanRepository;

        $this->service()->generateForUser(EntityBuilder::student(2), [
            ['sphereId' => 10, 'rating' => 6],
            ['sphereId' => 20, 'rating' => 5],
        ]);

        $this->assertCount(1, $this->persisted);
        $this->assertSame(2, $this->persisted[0]->getActivity()->getId());
    }

    public function testAucuneEtapeSansPreferenceEnregistree(): void
    {
        $this->ratingRepository->method('findRatingsOrderedByScore')->willReturn([]);

        $this->service()->generateForUser(EntityBuilder::student(1));

        $this->assertSame([], $this->persisted);
    }

    public function testLeCheminMarqueLesEtapesFaitesEtLEtapeCourante(): void
    {
        $this->parcoursRepository->method('findOrderedByUser')->willReturn([
            ['activityId' => 1, 'stepOrder' => 1, 'isAvailable' => '1', 'priority' => 1, 'sphereId' => 10],
            ['activityId' => 2, 'stepOrder' => 2, 'isAvailable' => '1', 'priority' => 1, 'sphereId' => 10],
            ['activityId' => 3, 'stepOrder' => 3, 'isAvailable' => '1', 'priority' => 2, 'sphereId' => 20],
        ]);
        $scanRepository = $this->createStub(ScanRepository::class);
        $scanRepository->method('findActivityIdsByUser')->willReturn([1]);
        $this->scanRepository = $scanRepository;

        $path = $this->service()->getPathForMap(EntityBuilder::student());

        $this->assertSame([1], $path['scannedIds']);
        $this->assertTrue($path['steps'][0]['done']);
        $this->assertFalse($path['steps'][0]['current']);
        $this->assertTrue($path['steps'][1]['current'], 'La 1re étape non faite devient l\'étape courante.');
        $this->assertFalse($path['steps'][2]['current']);
    }

    public function testUneEtapeIndisponibleEstIgnoreePourLEtapeCourante(): void
    {
        $this->parcoursRepository->method('findOrderedByUser')->willReturn([
            ['activityId' => 1, 'stepOrder' => 1, 'isAvailable' => '0', 'priority' => 1, 'sphereId' => 10],
            ['activityId' => 2, 'stepOrder' => 2, 'isAvailable' => '1', 'priority' => 1, 'sphereId' => 10],
        ]);

        $path = $this->service()->getPathForMap(EntityBuilder::student());

        $this->assertFalse($path['steps'][0]['current']);
        $this->assertTrue($path['steps'][1]['current']);
    }

    public function testSiToutEstIndisponibleLaPremiereEtapeNonFaiteResteCourante(): void
    {
        $this->parcoursRepository->method('findOrderedByUser')->willReturn([
            ['activityId' => 1, 'stepOrder' => 1, 'isAvailable' => '0', 'priority' => 1, 'sphereId' => 10],
            ['activityId' => 2, 'stepOrder' => 2, 'isAvailable' => '0', 'priority' => 1, 'sphereId' => 10],
        ]);

        $path = $this->service()->getPathForMap(EntityBuilder::student());

        $this->assertTrue($path['steps'][0]['current']);
    }

    public function testUneSphereSatureeFaitPasserALaSuivanteDuTop3(): void
    {
        $this->parcoursRepository->method('findOrderedByUser')->willReturn([
            ['activityId' => 1, 'stepOrder' => 1, 'isAvailable' => '1', 'priority' => 1, 'sphereId' => 10],
            ['activityId' => 2, 'stepOrder' => 2, 'isAvailable' => '1', 'priority' => 2, 'sphereId' => 20],
        ]);
        $this->scanRepository = $this->chargeSpheres([10 => 100]);

        $path = $this->service()->getPathForMap(EntityBuilder::student());

        $this->assertFalse($path['steps'][0]['current']);
        $this->assertTrue($path['steps'][1]['current'], 'Sphère 10 pleine : on passe à la suivante du top 3.');
    }

    public function testQuandToutLeTop3EstSatureOnViseLaSphereLaMoinsChargee(): void
    {
        $this->parcoursRepository->method('findOrderedByUser')->willReturn([
            ['activityId' => 1, 'stepOrder' => 1, 'isAvailable' => '1', 'priority' => 1, 'sphereId' => 10],
            ['activityId' => 2, 'stepOrder' => 2, 'isAvailable' => '1', 'priority' => 2, 'sphereId' => 20],
            ['activityId' => 3, 'stepOrder' => 3, 'isAvailable' => '1', 'priority' => 3, 'sphereId' => 30],
        ]);
        $this->scanRepository = $this->chargeSpheres([10 => 150, 20 => 120, 30 => 110]);

        $path = $this->service()->getPathForMap(EntityBuilder::student());

        $this->assertTrue($path['steps'][2]['current'], 'Rien n\'est imposé : on vise la sphère la moins chargée du top 3.');
    }

    /** @param array<int, int> $charge */
    private function chargeSpheres(array $charge): ScanRepository&Stub
    {
        $repository = $this->createStub(ScanRepository::class);
        $repository->method('findActivityIdsByUser')->willReturn([]);
        $repository->method('countRecentGroupedBySphere')->willReturn($charge);

        return $repository;
    }

    public function testCheminVideQuandAucunParcoursNEstGenere(): void
    {
        $this->parcoursRepository->method('findOrderedByUser')->willReturn([]);

        $path = $this->service()->getPathForMap(EntityBuilder::student());

        $this->assertSame([], $path['steps']);
        $this->assertSame([], $path['scannedIds']);
    }
}
