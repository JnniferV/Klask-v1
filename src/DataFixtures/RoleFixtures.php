<?php

namespace App\DataFixtures;

use App\Entity\Role;
use App\Security\RoleSecurity;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class RoleFixtures extends Fixture
{
    public const ROLE_ADMIN_REFERENCE        = 'role_admin';
    public const ROLE_ACCOMPANYING_REFERENCE = 'role_accompanying';
    public const ROLE_STUDENT_REFERENCE      = 'role_student';

    public function load(ObjectManager $manager): void
    {
        foreach ($this->getRoles() as [$name, $reference]) {
            $role = new Role();
            $role->setNameRole($name);
            $manager->persist($role);
            $this->addReference($reference, $role);
        }

        $manager->flush();
    }

    private function getRoles(): array
    {
        return [
            [RoleSecurity::ADMIN->value,        self::ROLE_ADMIN_REFERENCE],
            [RoleSecurity::ACCOMPANYING->value,  self::ROLE_ACCOMPANYING_REFERENCE],
            [RoleSecurity::STUDENT->value,       self::ROLE_STUDENT_REFERENCE],
        ];
    }
}
