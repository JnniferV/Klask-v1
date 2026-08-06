<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture implements DependentFixtureInterface
{

    public function load(ObjectManager $manager): void
    {
    }

    public function getDependencies(): array
    {
        return [
            RoleFixtures::class,
            AuthorityFixtures::class,
            StaffUserFixtures::class,
            EstablishmentFixtures::class,
            EventFixtures::class,
            GroupFixtures::class,
            AppParameterFixtures::class,
            SphereFixtures::class,
            ActivityCategoryFixtures::class,
            ActivityFixtures::class,
        ];
    }
}
