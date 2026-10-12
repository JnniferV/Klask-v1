<?php

namespace App\DataFixtures;

use App\Entity\Event;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class EventFixtures extends Fixture
{
    public const EVENT_AFTERNOON = 'event_apres_midi';

    // une seule session, toute la journée de 8h à 19h
    /** @var array<string, array{0: string, 1: string, 2: string}> */
    private const EVENTS = [
        self::EVENT_AFTERNOON => ['Klask 2026 — Après-midi (3ᵉ et +)', '08:00', '19:00'],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::EVENTS as $reference => [$name, $begin, $end]) {
            $event = (new Event())
                ->setName($name)
                ->setBeginningHourEvent(new \DateTimeImmutable('today '.$begin))
                ->setEndHourEvent(new \DateTimeImmutable('today '.$end));

            $manager->persist($event);
            $this->addReference($reference, $event);
        }

        $manager->flush();
    }
}
