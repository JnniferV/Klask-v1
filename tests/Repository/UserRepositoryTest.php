<?php

namespace App\Tests\Repository;

use App\Repository\UserRepository;
use App\Tests\Support\DbFixture;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserRepositoryTest extends KernelTestCase
{
    private UserRepository $repository;
    private DbFixture $fixture;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->repository = self::getContainer()->get(UserRepository::class);
        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->fixture = new DbFixture($this->em);
    }

    public function testUnElevePeutEtreChargeParSonPseudo(): void
    {
        $student = $this->fixture->student();

        $found = $this->repository->findByIdentifierEager((string) $student->getPseudo());

        $this->assertSame($student->getId(), $found?->getId());
    }

    public function testLeChargementRamenneLesRolesSansRequeteSupplementaire(): void
    {
        $student = $this->fixture->student();

        $found = $this->repository->findByIdentifierEager((string) $student->getPseudo());

        $this->assertSame(['ROLE_STUDENT'], $found?->getRoles());
    }

    public function testUnIdentifiantInconnuNeRamenePersonne(): void
    {
        $this->assertNull($this->repository->findByIdentifierEager('personne@nulle.part'));
    }

    public function testLesPseudosDejaAttribuesSontListes(): void
    {
        $student = $this->fixture->student();

        $this->assertContains($student->getPseudo(), $this->repository->findTakenPseudos());
    }

    // scalarResult → string
    public function testLesDatesDUnGroupeSontRenduesTypeesEtNonEnChaines(): void
    {
        $student = $this->fixture->student();
        $student->setBlockedUntil(new \DateTimeImmutable('+5 minutes'));
        $this->fixture->scan($student, $this->fixture->stand('token-types-sidebar'));
        $this->em->flush();

        $ligne = $this->repository->findStudentScoresByGroup($student->getGroup())[0];

        $this->assertInstanceOf(\DateTimeImmutable::class, $ligne['blockedUntil']);
        $this->assertIsInt($ligne['lastScanAt']);
        $this->assertIsInt($ligne['score']);
    }

    public function testLesScoresDUnGroupeSontTriesDuMeilleurAuMoinsBon(): void
    {
        $premier = $this->fixture->student();
        $group = $premier->getGroup();
        $second = $this->fixture->student();
        $second->setGroup($group)->setScore(10);
        $premier->setScore(80);
        $this->em->flush();

        $scores = $this->repository->findStudentScoresByGroup($group);

        $this->assertSame([80, 10], array_column($scores, 'score'));
    }

    public function testLeStaffDuGroupeNestPasListeAvecLesEleves(): void
    {
        $student = $this->fixture->student();
        $staff = $this->fixture->user('ACCOMPANYING');
        $staff->setGroup($student->getGroup())->setPseudo(null);
        $this->em->flush();

        $scores = $this->repository->findStudentScoresByGroup($student->getGroup());

        $this->assertSame([$student->getId()], array_column($scores, 'id'));
    }

    public function testLesElevesSontComptesParEtablissementEtNiveau(): void
    {
        $premier = $this->fixture->student();
        $group = $premier->getGroup();
        $this->fixture->student()->setGroup($group);
        $this->em->flush();

        $rows = $this->repository->findStudentsByLevelAndEstablishment();

        $this->assertCount(1, $rows);
        $this->assertSame($group->getEstablishment()->getName(), $rows[0]['establishment']);
        $this->assertSame($group->getName(), $rows[0]['level']);
        $this->assertSame(2, (int) $rows[0]['students']);
    }
}
