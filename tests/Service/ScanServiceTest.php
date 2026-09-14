<?php

namespace App\Tests\Service;

use App\Entity\Activity;
use App\Entity\ActivityCategory;
use App\Entity\Scan;
use App\Repository\ActivityRepository;
use App\Repository\GroupRepository;
use App\Repository\ParcoursRepository;
use App\Repository\ScanRepository;
use App\Service\AppParameterService;
use App\Service\ParcoursService;
use App\Service\RealtimeNotifier;
use App\Service\ScanService;
use App\Service\UserService;
use App\Tests\Support\EntityBuilder;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;

class ScanServiceTest extends TestCase
{
    private const TOKEN = 'token-valide';

    private ActivityRepository&Stub $activityRepository;
    private ScanRepository&Stub $scanRepository;
    private ParcoursRepository&Stub $parcoursRepository;
    private UserService&Stub $userService;
    private GroupRepository&MockObject $groupRepository;

    protected function setUp(): void
    {
        $this->activityRepository = $this->createStub(ActivityRepository::class);
        $this->scanRepository = $this->createStub(ScanRepository::class);
        $this->parcoursRepository = $this->createStub(ParcoursRepository::class);
        $this->userService = $this->createStub(UserService::class);
        $this->groupRepository = $this->createMock(GroupRepository::class);

        $this->scanRepository->method('findActivityIdsByUser')->willReturn([]);
        $this->scanRepository->method('findLastAt')->willReturn(null);
        $this->parcoursRepository->method('findOrderedByUser')->willReturn([]);
        $this->userService->method('getTopAndBottomSphereIds')->willReturn(['top' => [], 'bottom' => []]);
    }

    private function service(?EntityManagerInterface $em = null): ScanService
    {
        $params = $this->createStub(AppParameterService::class);
        $params->method('getInt')->willReturnCallback(static fn (string $key, int $default = 0) => $default);

        return new ScanService(
            $this->activityRepository,
            $this->scanRepository,
            $this->parcoursRepository,
            $em ?? $this->createStub(EntityManagerInterface::class),
            $this->createStub(ParcoursService::class),
            $this->userService,
            $params,
            $this->notifier(),
            $this->groupRepository,
        );
    }

    private function notifier(): RealtimeNotifier
    {
        return new RealtimeNotifier(
            $this->createStub(HubInterface::class),
            $this->createStub(LoggerInterface::class),
        );
    }

    private function stand(int $id = 10, int $points = 50, int $sphereId = 100): Activity
    {
        return EntityBuilder::activity(
            $id,
            EntityBuilder::category(ActivityCategory::TYPE_STAND, $points),
            EntityBuilder::sphere($sphereId),
        );
    }

    private function expectFoundActivity(Activity $activity): void
    {
        $this->activityRepository->method('findOneByQrcodeToken')->willReturn($activity);
    }

    public function testRefuseUnCompteTemporairementBloque(): void
    {
        $user = EntityBuilder::student();
        $user->setBlockedUntil(new \DateTimeImmutable('+5 minutes'));

        $result = $this->service()->process($user, self::TOKEN);

        $this->assertFalse($result->ok);
        $this->assertSame('Compte temporairement bloqué.', $result->error);
    }

    public function testQrInconnuIncrementeLeCompteurDeScansInvalides(): void
    {
        $this->activityRepository->method('findOneByQrcodeToken')->willReturn(null);
        $user = EntityBuilder::student();

        $result = $this->service()->process($user, 'inconnu');

        $this->assertFalse($result->ok);
        $this->assertSame('QR invalide.', $result->error);
        $this->assertSame(1, $user->getInvalidScanCount());
        $this->assertNull($user->getBlockedUntil());
    }

    public function testBlocageAuTroisiemeQrInvalide(): void
    {
        $this->activityRepository->method('findOneByQrcodeToken')->willReturn(null);
        $user = EntityBuilder::student();
        $user->setInvalidScanCount(2); // seuil = 3

        $this->service()->process($user, 'inconnu');

        $this->assertNotNull($user->getBlockedUntil());
        $this->assertSame(0, $user->getInvalidScanCount(), 'Le compteur repart à zéro après blocage.');
    }

    public function testRefuseUnStandFerme(): void
    {
        $stand = $this->stand();
        $stand->setIsAvailable(false);
        $this->expectFoundActivity($stand);

        $result = $this->service()->process(EntityBuilder::student(), self::TOKEN);

        $this->assertFalse($result->ok);
        $this->assertSame('Stand fermé.', $result->error);
    }

    public function testRefuseUnStandDejaScanne(): void
    {
        $this->expectFoundActivity($this->stand());
        $this->scanRepository->method('existsForUserAndActivity')->willReturn(true);

        $result = $this->service()->process(EntityBuilder::student(), self::TOKEN);

        $this->assertFalse($result->ok);
        $this->assertSame('Déjà scanné.', $result->error);
    }

    // régression double soumission
    public function testUneInsertionConcurrenteRendLeMemeVerdictQueLeGarde(): void
    {
        $this->expectFoundActivity($this->stand());
        $this->scanRepository->method('existsForUserAndActivity')->willReturn(false);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush')->willThrowException($this->createStub(UniqueConstraintViolationException::class));

        $result = $this->service($em)->process(EntityBuilder::student(), self::TOKEN);

        $this->assertFalse($result->ok);
        $this->assertSame('already_scanned', $result->code);
        $this->assertSame(0, $result->points);
    }

    public function testRefuseUnStandSatureParLaLimiteDure(): void
    {
        $stand = $this->stand();
        $stand->setHardLimit(2);
        $this->expectFoundActivity($stand);
        $this->scanRepository->method('countRecentForActivity')->willReturn(2);

        $result = $this->service()->process(EntityBuilder::student(), self::TOKEN);

        $this->assertFalse($result->ok);
        $this->assertSame('Stand complet — repasse dans quelques minutes.', $result->error);
    }

    public function testRefuseUnScanTropRapprocheDuPrecedent(): void
    {
        $this->expectFoundActivity($this->stand());
        $scanRepository = $this->createStub(ScanRepository::class);
        $scanRepository->method('findLastAt')->willReturn(new \DateTimeImmutable('-1 minute'));
        $scanRepository->method('findActivityIdsByUser')->willReturn([]);
        $this->scanRepository = $scanRepository;

        $result = $this->service()->process(EntityBuilder::student(), self::TOKEN);

        $this->assertFalse($result->ok);
        $this->assertSame('Attends encore un peu avant le prochain scan.', $result->error);
    }

    public function testUnStandDeTypeStageEchappeAuDelaiEntreDeuxScans(): void
    {
        $stage = $this->stand();
        $stage->setIsInternship(true);
        $this->expectFoundActivity($stage);

        $scanRepository = $this->createStub(ScanRepository::class);
        $scanRepository->method('findLastAt')->willReturn(new \DateTimeImmutable('-1 minute'));
        $scanRepository->method('findActivityIdsByUser')->willReturn([]);
        $this->scanRepository = $scanRepository;

        $result = $this->service()->process(EntityBuilder::student(), self::TOKEN);

        $this->assertTrue($result->ok);
    }

    public function testStandDansLeTop3RapporteLesPointsPleins(): void
    {
        $this->expectFoundActivity($this->stand(10, 50, 100));
        $this->userService = $this->createStub(UserService::class);
        $this->userService->method('getTopAndBottomSphereIds')->willReturn(['top' => [100, 101, 102], 'bottom' => []]);

        $result = $this->service()->process(EntityBuilder::student(), self::TOKEN);

        $this->assertTrue($result->ok);
        $this->assertSame(50, $result->points);
    }

    public function testStandHorsTop3Rapporte25Points(): void
    {
        $this->expectFoundActivity($this->stand(10, 50, 999));
        $this->userService = $this->createStub(UserService::class);
        $this->userService->method('getTopAndBottomSphereIds')->willReturn(['top' => [100, 101, 102], 'bottom' => []]);

        $result = $this->service()->process(EntityBuilder::student(), self::TOKEN);

        $this->assertSame(25, $result->points);
    }

    public function testUneActiviteHorsSphereRapporteLesPointsDeSaCategorie(): void
    {
        $atelier = EntityBuilder::activity(20, EntityBuilder::category(ActivityCategory::TYPE_ATELIER, 15));
        $this->expectFoundActivity($atelier);

        $result = $this->service()->process(EntityBuilder::student(), self::TOKEN);

        $this->assertSame(15, $result->points);
        $this->assertSame(0, $result->bonus);
    }

    private function expectCarte(): void
    {
        $this->activityRepository->method('findMapIdsByStandFlag')
            ->willReturn([1 => true, 2 => true, 3 => true, 4 => true, 5 => true, 6 => true, 7 => false]);
    }

    private function expectParcours(): void
    {
        $parcours = $this->createStub(ParcoursRepository::class);
        $parcours->method('findOrderedByUser')->willReturn(array_map(
            static fn (int $id) => ['activityId' => $id, 'priority' => $id <= 3 ? $id : 0],
            [1, 2, 3, 4, 5, 6]
        ));
        $this->parcoursRepository = $parcours;
    }

    /** @param int[] $ids */
    private function expectDejaScannes(array $ids): void
    {
        $scanRepository = $this->createStub(ScanRepository::class);
        $scanRepository->method('findActivityIdsByUser')->willReturn($ids);
        $scanRepository->method('findLastAt')->willReturn(null);
        $this->scanRepository = $scanRepository;
    }

    public function testBonusQuandLeScanTermineLesTroisSpheresPreferees(): void
    {
        $this->expectFoundActivity($this->stand(3));
        $this->expectCarte();
        $this->expectParcours();
        $this->expectDejaScannes([1, 2]);

        $result = $this->service()->process(EntityBuilder::student(), self::TOKEN);

        $this->assertSame(50, $result->bonus, 'BONUS_TOP3_SPHERES seul : les autres sphères restent à faire.');
    }

    public function testBonusQuandLeScanTermineToutesLesSpheresSansCrediterDeuxFoisLeTop3(): void
    {
        $this->expectFoundActivity($this->stand(6));
        $this->expectCarte();
        $this->expectParcours();
        $this->expectDejaScannes([1, 2, 3, 4, 5]);

        $result = $this->service()->process(EntityBuilder::student(), self::TOKEN);

        $this->assertSame(100, $result->bonus, 'BONUS_ALL_SPHERES seul : le top 3 a déjà été crédité, l\'atelier reste à faire.');
    }

    public function testBonusMaximalQuandLAtelierTermineLaCarte(): void
    {
        $this->expectFoundActivity(EntityBuilder::activity(7, EntityBuilder::category(ActivityCategory::TYPE_ATELIER, 15)));
        $this->expectCarte();
        $this->expectParcours();
        $this->expectDejaScannes([1, 2, 3, 4, 5, 6]);

        $result = $this->service()->process(EntityBuilder::student(), self::TOKEN);

        $this->assertSame(150, $result->bonus, 'BONUS_MAX_SCORE seul : sphères déjà créditées, l\'atelier complète la carte.');
    }

    public function testLeScanValideCrediteLEleveEtDelegueLeCreditDuGroupe(): void
    {
        $this->expectFoundActivity($this->stand(10, 50));
        $group = EntityBuilder::group(1, 200);
        $user = EntityBuilder::student(1, $group, 30);

        // score via UPDATE atomique
        $this->groupRepository->expects($this->once())->method('addScore')->with($group, 50);

        $result = $this->service()->process($user, self::TOKEN);

        $this->assertTrue($result->ok);
        $this->assertSame(80, $user->getScore());
        $this->assertSame(80, $result->userScore);
    }

    public function testLeScanValideEstEnregistreAvecSonActiviteSonEleveEtSaDate(): void
    {
        $stand = $this->stand(10);
        $this->expectFoundActivity($stand);
        $user = EntityBuilder::student();

        $persisted = null;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted = $entity;
        });

        $params = $this->createStub(AppParameterService::class);
        $params->method('getInt')->willReturnCallback(static fn (string $key, int $default = 0) => $default);

        $service = new ScanService(
            $this->activityRepository,
            $this->scanRepository,
            $this->parcoursRepository,
            $em,
            $this->createStub(ParcoursService::class),
            $this->userService,
            $params,
            $this->notifier(),
            $this->groupRepository,
        );
        $service->process($user, self::TOKEN);

        $this->assertInstanceOf(Scan::class, $persisted);
        $this->assertSame($stand, $persisted->getActivity());
        $this->assertSame($user, $persisted->getUser());
        $this->assertEqualsWithDelta(time(), $persisted->getHourValidation()->getTimestamp(), 5);
    }

    public function testUnScanValideRemetAZeroLeCompteurDeScansInvalides(): void
    {
        $this->expectFoundActivity($this->stand());
        $user = EntityBuilder::student();
        $user->setInvalidScanCount(2);

        $this->service()->process($user, self::TOKEN);

        $this->assertSame(0, $user->getInvalidScanCount());
    }

    public function testLeCacheDuParcoursEstInvalideApresUnScanValide(): void
    {
        $this->expectFoundActivity($this->stand());
        $user = EntityBuilder::student();
        $params = $this->createStub(AppParameterService::class);
        $params->method('getInt')->willReturnCallback(static fn (string $key, int $default = 0) => $default);

        $parcoursService = $this->createMock(ParcoursService::class);
        $parcoursService->expects($this->once())->method('invalidatePath')->with($user);

        $service = new ScanService(
            $this->activityRepository,
            $this->scanRepository,
            $this->parcoursRepository,
            $this->createStub(EntityManagerInterface::class),
            $parcoursService,
            $this->userService,
            $params,
            $this->notifier(),
            $this->groupRepository,
        );

        $service->process($user, self::TOKEN);
    }
}
