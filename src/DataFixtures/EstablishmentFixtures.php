<?php

namespace App\DataFixtures;

use App\Entity\Establishment;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class EstablishmentFixtures extends Fixture
{
    public const ESTABLISHMENT_REFERENCE = 'establishment';

    // établissements inscrits, noms tels que saisis
    /** @var list<string> */
    private const NAMES = [
        // matin
        'Primaire CM2', // nom réel non communiqué, provisoire
        'Collège Germain Pensivy / Rosporden',
        'La Tourelle',
        // après-midi, Le Porzou est sur les deux sessions
        'collège Le Porzou',
        'collège François Collobert',
        'Collège St Michel',
        'LYCÉE BRIZEUX',
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
