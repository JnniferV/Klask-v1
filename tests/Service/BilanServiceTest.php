<?php

namespace App\Tests\Service;

use App\Entity\ActivityCategory;
use App\Entity\Scan;
use App\Entity\User;
use App\Dto\BilanEleve;
use App\Dto\BilanGroupe;
use App\Repository\ScanRepository;
use App\Repository\UserRepository;
use App\Service\BilanService;
use App\Tests\Support\EntityBuilder;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class BilanServiceTest extends TestCase
{
    private ScanRepository&Stub $scanRepository;
    private UserRepository&Stub $userRepository;

    protected function setUp(): void
    {
        $this->scanRepository = $this->createStub(ScanRepository::class);
        $this->userRepository = $this->createStub(UserRepository::class);
        $this->userRepository->method('countStudentsByGroup')->willReturn(12);
    }

    private function service(): BilanService
    {
        return new BilanService($this->scanRepository, $this->userRepository);
    }

    /** @return Scan[] */
    private function scans(int $nombre): array
    {
        $category = EntityBuilder::category(ActivityCategory::TYPE_STAND);
        $student = EntityBuilder::student();

        return array_map(
            fn (int $i): Scan => new Scan(new \DateTimeImmutable(), EntityBuilder::activity($i, $category), $student),
            range(1, $nombre)
        );
    }

    private function bilanEleve(int $nombreDeScans, ?string $premierScanIlYA = null): BilanEleve
    {
        $this->scanRepository->method('findByUserWithDetails')->willReturn($this->scans($nombreDeScans));
        $this->scanRepository->method('findFirstAt')->willReturn(
            null === $premierScanIlYA ? null : new \DateTimeImmutable($premierScanIlYA)
        );

        return $this->service()->buildStudentBilan(EntityBuilder::student(1, EntityBuilder::group(1, 500), 80));
    }

    public function testLeBilanEleveReprendSonScoreEtCeluiDeSonGroupe(): void
    {
        $bilan = $this->bilanEleve(1, '-10 minutes');

        $this->assertSame(80, $bilan->scorePerso);
        $this->assertSame(500, $bilan->scoreGroupe);
    }

    public function testTroisStandsDeverrouillentLePdfEleve(): void
    {
        $this->assertTrue($this->bilanEleve(3)->isPdfUnlocked);
    }

    public function testDeuxStandsEnMoinsDUneHeureNeDeverrouillentPasLePdf(): void
    {
        $this->assertFalse($this->bilanEleve(2, '-10 minutes')->isPdfUnlocked);
    }

    public function testUneHeureDePresenceDeverrouilleLePdfDesLePremierStand(): void
    {
        $this->assertTrue($this->bilanEleve(1, '-2 hours')->isPdfUnlocked);
    }

    public function testAucunScanNeDeverrouilleJamaisLePdf(): void
    {
        $this->assertFalse($this->bilanEleve(0)->isPdfUnlocked);
    }

    public function testUnAccompagnateurSansGroupeNAAucunBilan(): void
    {
        $bilan = $this->service()->buildGroupBilan(new User());

        $this->assertFalse($bilan->hasGroup);
        $this->assertFalse($bilan->isPdfUnlocked);
        $this->assertSame([], $bilan->activitiesStats);
    }

    private function bilanGroupe(array $rows): BilanGroupe
    {
        $this->scanRepository->method('findGroupScanRows')->willReturn($rows);

        return $this->service()->buildGroupBilan(EntityBuilder::student(1, EntityBuilder::group(1, 240)));
    }

    private function ligne(string $sphere, string $activite, string $description): array
    {
        return [
            'sphereName' => $sphere,
            'activityName' => $activite,
            'activityDescription' => $description,
            'categoryType' => ActivityCategory::TYPE_STAND,
        ];
    }

    public function testLesActivitesSontComptesParEleveEtTrieesDeLaPlusVisiteeALaMoins(): void
    {
        $bilan = $this->bilanGroupe([
            $this->ligne('CRÉATIF', 'Stand CRÉATIF', 'Céramique et design.'),
            $this->ligne('RIGOUREUX', 'Stand RIGOUREUX', 'Droit et audit.'),
            $this->ligne('CRÉATIF', 'Stand CRÉATIF', 'Céramique et design.'),
        ]);

        $this->assertSame(['Stand CRÉATIF', 'Stand RIGOUREUX'], array_keys($bilan->activitiesStats));
        $this->assertSame(2, $bilan->activitiesStats['Stand CRÉATIF']->count);
        $this->assertSame(3, $bilan->totalScans);
    }

    public function testChaqueActiviteRemonteSonDescriptifEtSaCategorie(): void
    {
        $stats = $this->bilanGroupe([$this->ligne('CRÉATIF', 'Stand CRÉATIF', 'Céramique et design.')]);

        $this->assertSame('Céramique et design.', $stats->activitiesStats['Stand CRÉATIF']->description);
        $this->assertSame(ActivityCategory::TYPE_STAND, $stats->activitiesStats['Stand CRÉATIF']->category);
    }

    public function testLesVisitesSontAussiRegroupeesParSphere(): void
    {
        $bilan = $this->bilanGroupe([
            $this->ligne('CRÉATIF', 'Stand CRÉATIF', 'Céramique.'),
            $this->ligne('CRÉATIF', 'Atelier CRÉATIF', 'Sérigraphie.'),
        ]);

        $this->assertSame(['CRÉATIF' => 2], $bilan->spheresStats);
    }

    public function testLePdfDeGroupeSeDeverrouilleAuTroisiemeScanDeLaClasse(): void
    {
        $ligne = $this->ligne('CRÉATIF', 'Stand CRÉATIF', 'Céramique.');

        $this->assertTrue($this->bilanGroupe([$ligne, $ligne, $ligne])->isPdfUnlocked);
    }
}
