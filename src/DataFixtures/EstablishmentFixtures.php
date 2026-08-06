<?php

namespace App\DataFixtures;

use App\Entity\Establishment;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class EstablishmentFixtures extends Fixture
{
    public const ESTABLISHMENT_REFERENCE = 'establishment';

    private const NAMES = [
        'Lycée Jean-Marie Le Bris - Douarnenez',
        'Lycée Saint-Joseph - Concarneau',
        'Lycée Pierre Guéguin - Concarneau',
        'Lycée Saint-Gabriel - Pont-l\'Abbé',
        'Lycée Laennec - Pont-l\'Abbé',
        'Lycée Le Paraclet - Quimper',
        'Lycée Sainte-Thérèse - Quimper',
        'Lycée Le Likès - Quimper',
        'Lycée Yves Thépot - Quimper',
        'Lycée de Cornouaille - Quimper',
        'Lycée Brizeux - Quimper',
        'Lycée Chaptal - Quimper',
        'Collège Saint-Blaise - Douarnenez',
        'Collège des Sables Blancs - Concarneau',
        'Collège Saint-Joseph - Fouesnant',
        'Collège de Kervihan - Fouesnant',
        'Collège Laennec - Pont-l\'Abbé',
        'Collège Diwan - Quimper',
        'Collège Sainte-Thérèse - Quimper',
        'Collège Saint-Jean Baptiste - Quimper',
        'Collège Saint-Yves - Quimper',
        'Collège Brizeux - Quimper',
        'Collège Max Jacob - Quimper',
        'Collège La Tour d\'Auvergne - Quimper',
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::NAMES as $index => $name) {
            $establishment = new Establishment();
            $establishment->setName($name);

            $manager->persist($establishment);
            $this->addReference(self::ESTABLISHMENT_REFERENCE . '_' . $index, $establishment);
        }

        $manager->flush();
    }
}
