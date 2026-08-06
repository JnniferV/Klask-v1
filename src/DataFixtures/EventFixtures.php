<?php

namespace App\DataFixtures;

use App\Entity\Event;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class EventFixtures extends Fixture
{
    public const EVENT_REFERENCE = 'event';

    public function load(ObjectManager $manager): void
    {
        // Event principal (après-midi) : 13h30 → 17h00
        $event = new Event();
        $event->setName('Klask 2026 — Après-midi');
        $event->setBeginningHourEvent(new \DateTimeImmutable('today 13:30'));
        $event->setEndHourEvent(new \DateTimeImmutable('today 17:00'));

        $manager->persist($event);
        $this->addReference(self::EVENT_REFERENCE, $event);

        $manager->flush();
    }
}
