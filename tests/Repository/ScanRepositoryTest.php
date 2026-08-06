<?php

namespace App\Tests\Repository;

use App\Entity\Activity;
use App\Entity\User;
use App\Repository\ScanRepository;
use App\Tests\Support\DbFixture;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ScanRepositoryTest extends KernelTestCase
{
    private ScanRepository $repository;
    private DbFixture $fixture;
    private User $student;
    private Activity $stand;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->repository = self::getContainer()->get(ScanRepository::class);
        $this->fixture    = new DbFixture(self::getContainer()->get('doctrine')->getManager());
        $this->student    = $this->fixture->student();
        $this->stand      = $this->fixture->stand('token-scan-repo');
    }

    public function testUnStandNonScanneNEstPasDetecte(): void
    {
        $this->assertFalse($this->repository->existsForUserAndActivity($this->student, $this->stand));
    }

    public function testUnStandScanneEstDetecte(): void
    {
        $this->fixture->scan($this->student, $this->stand);

        $this->assertTrue($this->repository->existsForUserAndActivity($this->student, $this->stand));
    }

    public function testLesActivitesScanneesRemontentDuPlusRecentAuPlusAncien(): void
    {
        $autre = $this->fixture->stand('token-scan-repo-2');
        $this->fixture->scan($this->student, $this->stand, '-1 hour');
        $this->fixture->scan($this->student, $autre, '-5 minutes');

        $this->assertSame(
            [$autre->getId(), $this->stand->getId()],
            $this->repository->findActivityIdsByUser($this->student)
        );
    }

    public function testLOccupationIgnoreLesScansHorsFenetre(): void
    {
        $this->fixture->scan($this->student, $this->stand, '-1 hour');

        $this->assertSame(0, $this->repository->countRecentForActivity($this->stand));

        $this->fixture->scan($this->fixture->student(), $this->stand, '-2 minutes');

        $this->assertSame(1, $this->repository->countRecentForActivity($this->stand));
    }

    public function testLaDateDuDernierScanEstNulleSansAucunScan(): void
    {
        $this->assertNull($this->repository->findLastAt($this->student));
    }

    public function testLaDateDuDernierScanEstLaPlusRecente(): void
    {
        $this->fixture->scan($this->student, $this->stand, '-1 hour');
        $this->fixture->scan($this->student, $this->fixture->stand('token-scan-repo-3'), '-2 minutes');

        $this->assertEqualsWithDelta(
            (new \DateTimeImmutable('-2 minutes'))->getTimestamp(),
            $this->repository->findLastAt($this->student)->getTimestamp(),
            5
        );
    }
}
