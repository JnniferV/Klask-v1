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
        'Troisième' => '#F5A623',
        'Seconde' => '#4A90E2',
        'Première' => '#7ED321',
        'Terminale' => '#34495E',
    ];

    // établissement => niveau => nombre de classes, tout sur la session unique
    /** @var array<string, array<string, int>> */
    private const SCHOOLS = [
        'Collège Germain Pensivy / Rosporden' => ['Troisième' => 5],
        'Collège La Tourelle' => ['Troisième' => 3],
        'Collège Le Porzou' => ['Troisième' => 2],
        'Collège François Collobert' => ['Troisième' => 3],
        'Collège St Michel' => ['Troisième' => 1],
        'Lycée Brizeux' => ['Première' => 6],
        // 4 classes STMG, répartition non précisée
        'Le Likès La Salle' => ['Première' => 2, 'Terminale' => 2],
    ];

    public function load(ObjectManager $manager): void
    {
        $event = $this->getReference(EventFixtures::EVENT_AFTERNOON, Event::class);
        $counter = 1;

        foreach (self::SCHOOLS as $school => $levels) {
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

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [EventFixtures::class, EstablishmentFixtures::class];
    }
}
