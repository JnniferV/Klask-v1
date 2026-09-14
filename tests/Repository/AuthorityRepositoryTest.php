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
    private EntityManagerInterface $em;
    private AuthorityRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->repository = self::getContainer()->get(AuthorityRepository::class);
    }

    public function testFindByRoleReturnsCorrectAuthority(): void
    {
        $role = new Role();
        $role->setNameRole('ROLE_TEST_ADMIN');
        $this->em->persist($role);

        $authority = new Authority();
        $authority->setAuthorityUser('Accès Total');
        $this->em->persist($authority);

        $authorityRole = new AuthorityRole($authority, $role);
        $this->em->persist($authorityRole);

        $this->em->flush();

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

    public function testGetByRoleLeveUneExceptionSiLeRoleNexistePas(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->repository->getByRole('ROLE_NON_EXISTANT');
    }
}
