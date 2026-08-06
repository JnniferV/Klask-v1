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
        $this->em         = self::getContainer()->get('doctrine')->getManager();
        $this->fixture    = new DbFixture($this->em);
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

    public function testLesScoresDUnGroupeSontTriesDuMeilleurAuMoinsBon(): void
    {
        $premier = $this->fixture->student();
        $group   = $premier->getGroup();
        $second  = $this->fixture->student();
        $second->setGroup($group)->setScore(10);
        $premier->setScore(80);
        $this->em->flush();

        $scores = $this->repository->findStudentScoresByGroup($group);

        $this->assertSame([80, 10], array_column($scores, 'score'));
    }
}
