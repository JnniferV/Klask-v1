<?php

namespace App\DataFixtures;

use App\Entity\Sphere;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SphereFixtures extends Fixture
{
    /** @var array<string, string> name => couleur hex */
    private const SPHERES = [
        'CRÉATIF' => '#FBC52B',
        'RIGOUREUX' => '#0D65D7',
        'NOUVEAUTÉ' => '#F64851',
        'EXTÉRIEUR' => '#3E7C5B',
        'COMMUNIQUER' => '#7D59FB',
        'UTILE' => '#E55EBB',
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::SPHERES as $name => $color) {
            $sphere = new Sphere();
            $sphere->setName($name);
            $sphere->setColor($color);
            // 6 % => 12 % de large, les 6 sphères tiennent sans se chevaucher
            $sphere->setRadius(6.0);

            $manager->persist($sphere);
            $this->addReference('sphere_'.$name, $sphere);
        }

        $manager->flush();
    }
}
