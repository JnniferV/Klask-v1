<?php

namespace App\DataFixtures;

use App\Entity\Authority;
use App\Entity\AuthorityRole;
use App\Entity\Role;
use App\Security\RoleSecurity;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AuthorityFixtures extends Fixture implements DependentFixtureInterface
{
    public const AUTHORITY_ADMIN_REFERENCE        = 'authority_admin';
    public const AUTHORITY_ACCOMPANYING_REFERENCE = 'authority_accompanying';
    public const AUTHORITY_STUDENT_REFERENCE      = 'authority_student';

    public function load(ObjectManager $manager): void
    {
        foreach ($this->getAuthorityDefinitions() as [$roleName, $roleReference, $authorityReference]) {
            $authority = new Authority();
            $authority->setAuthorityUser($roleName);
            $manager->persist($authority);

            $authorityRole = new AuthorityRole($authority, $this->getReference($roleReference, Role::class));
            $manager->persist($authorityRole);

            $this->addReference($authorityReference, $authority);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [RoleFixtures::class];
    }

    /**
     * @return array<array{string, string, string}>
     */
    private function getAuthorityDefinitions(): array
    {
        return [
            [RoleSecurity::ADMIN->value,        RoleFixtures::ROLE_ADMIN_REFERENCE,        self::AUTHORITY_ADMIN_REFERENCE],
            [RoleSecurity::ACCOMPANYING->value,  RoleFixtures::ROLE_ACCOMPANYING_REFERENCE, self::AUTHORITY_ACCOMPANYING_REFERENCE],
            [RoleSecurity::STUDENT->value,       RoleFixtures::ROLE_STUDENT_REFERENCE,      self::AUTHORITY_STUDENT_REFERENCE],
        ];
    }
}
