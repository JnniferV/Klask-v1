<?php
namespace App\Tests\Repository;

use App\Entity\Authority;
use App\Entity\AuthorityRole;
use App\Entity\Role;
use App\Repository\AuthorityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AuthorityRepositoryTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager;
    private AuthorityRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        $this->repository = self::getContainer()->get(AuthorityRepository::class);
    }

    public function testFindByRoleReturnsCorrectAuthority(): void
    {
        $role = new Role();
        $role->setNameRole('ROLE_TEST_ADMIN');
        $this->entityManager->persist($role);

        $authority = new Authority();
        $authority->setAuthorityUser('Accès Total');
        $this->entityManager->persist($authority);

        $authorityRole = new AuthorityRole($authority, $role);
        $this->entityManager->persist($authorityRole);

        $this->entityManager->flush();

        $result = $this->repository->findByRole('ROLE_TEST_ADMIN');

        $this->assertNotNull($result);
        $this->assertInstanceOf(Authority::class, $result);
        $this->assertEquals('Accès Total', $result->getAuthorityUser());
    }

    public function testFindByRoleReturnsNullIfRoleDoesNotExist(): void
    {
        $result = $this->repository->findByRole('ROLE_NON_EXISTANT');
        $this->assertNull($result);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }
}
