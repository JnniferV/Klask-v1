<?php

namespace App\DataFixtures;

use App\Entity\Establishment;
use App\Entity\Event;
use App\Entity\Group;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class GroupFixtures extends Fixture implements DependentFixtureInterface
{
    // une couleur par niveau, fixtures seulement
    /** @var array<string, string> */
    private const COLORS = [
        'CM2' => '#9B59B6',
        'Sixième' => '#1ABC9C',
        'Cinquième' => '#E67E22',
        'Quatrième' => '#E74C3C',
        'Troisième' => '#F5A623',
        'Seconde' => '#4A90E2',
        'Première' => '#7ED321',
        'Terminale' => '#34495E',
    ];

    // event => établissement => niveau => nombre de classes
    /** @var array<string, array<string, array<string, int>>> */
    private const SESSIONS = [
        EventFixtures::EVENT_MORNING => [
            'Primaire CM2' => ['CM2' => 1],
            'Collège Germain Pensivy / Rosporden' => ['Quatrième' => 5],
            'La Tourelle' => ['Cinquième' => 3],
            // Le Porzou : 4ᵉ de réserve le matin, 4ᵉ + 3ᵉ l'après-midi
            'collège Le Porzou' => ['Quatrième' => 1],
        ],
        EventFixtures::EVENT_AFTERNOON => [
            'collège Le Porzou' => ['Quatrième' => 1, 'Troisième' => 1],
            'collège François Collobert' => ['Troisième' => 3],
            'Collège St Michel' => ['Troisième' => 1],
            'LYCÉE BRIZEUX' => ['Première' => 6],
            // 4 classes STMG, répartition non précisée
            'Le Likès La Salle' => ['Première' => 2, 'Terminale' => 2],
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        $counter = 1;

        foreach (self::SESSIONS as $eventReference => $schools) {
            $event = $this->getReference($eventReference, Event::class);

            foreach ($schools as $school => $levels) {
                $establishment = $this->getReference(
                    EstablishmentFixtures::ESTABLISHMENT_REFERENCE.'_'.$school,
                    Establishment::class
                );

                foreach ($levels as $level => $count) {
                    for ($i = 0; $i < $count; ++$i) {
                        $manager->persist(
                            (new Group())
                                ->setName($level)
                                ->setColor(self::COLORS[$level])
                                ->setCode(sprintf('GRP%04d', $counter++))
                                ->setEvent($event)
                                ->setEstablishment($establishment)
                        );
                    }
                }
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [EventFixtures::class, EstablishmentFixtures::class];
    }
}
