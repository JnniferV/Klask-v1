<?php

namespace App\DataFixtures;

use App\Entity\Sphere;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SphereFixtures extends Fixture
{
    /** @var array<string, string> name => couleur hex */
    private const SPHERES = [
        'CRÉATIF'     => '#E74C3C',
        'RIGOUREUX'   => '#3498DB',
        'NOUVEAUTÉ'   => '#9B59B6',
        'EXTÉRIEUR'   => '#27AE60',
        'COMMUNIQUER' => '#F39C12',
        'UTILE'       => '#1ABC9C',
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::SPHERES as $name => $color) {
            $sphere = new Sphere();
            $sphere->setName($name);
            $sphere->setColor($color);

            $manager->persist($sphere);
            $this->addReference('sphere_' . $name, $sphere);
        }

        $manager->flush();
    }
}
