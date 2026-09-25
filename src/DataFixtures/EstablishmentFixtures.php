<?php

namespace App\DataFixtures;

use App\Entity\Establishment;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class EstablishmentFixtures extends Fixture
{
    public const ESTABLISHMENT_REFERENCE = 'establishment';

    // établissements inscrits, écriture uniformisée : initiale en majuscule
    /** @var list<string> */
    private const NAMES = [
        'Collège Germain Pensivy / Rosporden',
        'Collège La Tourelle',
        'Collège Le Porzou',
        'Collège François Collobert',
        'Collège St Michel',
        'Lycée Brizeux',
        'Le Likès La Salle',
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::NAMES as $name) {
            $establishment = new Establishment();
            $establishment->setName($name);

            $manager->persist($establishment);
            // référencé par nom, pas par index
            $this->addReference(self::ESTABLISHMENT_REFERENCE.'_'.$name, $establishment);
        }

        $manager->flush();
    }
}
