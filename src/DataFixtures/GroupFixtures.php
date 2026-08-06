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
    public const GROUP_REFERENCE = 'group';

    private const LEVELS = [
        ['name' => 'Seconde',  'color' => '#4A90E2'],
        ['name' => 'Première', 'color' => '#7ED321'],
        ['name' => 'Troisième','color' => '#F5A623'],
    ];

    public function load(ObjectManager $manager): void
    {
        $counter = 1;

        for ($i = 0; $i < 24; $i++) {
            $levels = $i < 12
                ? [self::LEVELS[0], self::LEVELS[1]]
                : [self::LEVELS[2]];

            foreach ($levels as $level) {
                $group = new Group();
                $group->setName($level['name'])
                      ->setColor($level['color'])
                      ->setCode(sprintf('GRP%04d', $counter++))
                      ->setEvent($this->getReference(EventFixtures::EVENT_REFERENCE, Event::class))
                      ->setEstablishment($this->getReference(
                          EstablishmentFixtures::ESTABLISHMENT_REFERENCE . '_' . $i,
                          Establishment::class
                      ));

                $manager->persist($group);
                $this->addReference(self::GROUP_REFERENCE . '_' . $level['name'] . '_' . $i, $group);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [EventFixtures::class, EstablishmentFixtures::class];
    }
}
