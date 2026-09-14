<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\EventResetService;
use App\Tests\Support\DbFixture;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EventResetServiceTest extends KernelTestCase
{
    private EventResetService $service;
    private DbFixture $fixture;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get('doctrine')->getManager();
        $this->service = self::getContainer()->get(EventResetService::class);
        $this->fixture = new DbFixture($this->em);
    }

    private function compterEleves(): int
    {
        return $this->em->getRepository(User::class)->count([]);
    }

    public function testLaRemiseAZeroSupprimeLesElevesEtRemetLeScoreDuGroupeAZero(): void
    {
        $eleve = $this->fixture->student();
        $group = $eleve->getGroup();
        $group->setScore(320);
        $this->fixture->scan($eleve, $this->fixture->stand('token-reset'));
        $this->em->flush();

        $avant = $this->compterEleves();

        $counts = $this->service->reset($group->getEvent());
        $this->em->flush();
        $this->em->clear();

        $this->assertSame(1, $counts['Élèves supprimés']);
        $this->assertSame($avant - 1, $this->compterEleves());
        $this->assertSame(0, $this->em->getRepository(\App\Entity\Group::class)->find($group->getId())->getScore());
    }

    // reset isolé par event
    public function testLaRemiseAZeroNAffectePasUnAutreEvenement(): void
    {
        $conserve = $this->fixture->student();
        $conserve->getGroup()->setScore(150);
        $aEffacer = $this->fixture->student();
        $this->em->flush();

        $idConserve = $conserve->getId();
        $idGroupe = $conserve->getGroup()->getId();

        $this->service->reset($aEffacer->getGroup()->getEvent());
        $this->em->flush();
        $this->em->clear();

        $this->assertNotNull($this->em->getRepository(User::class)->find($idConserve));
        $this->assertSame(150, $this->em->getRepository(\App\Entity\Group::class)->find($idGroupe)->getScore());
    }

    public function testLaRemiseAZeroHorodateLEvenement(): void
    {
        $event = $this->fixture->student()->getGroup()->getEvent();
        $this->assertNull($event->getResetAt());

        $this->service->reset($event);

        $this->assertNotNull($event->getResetAt(), 'Sans horodatage, la remise à zéro peut rejouer en boucle.');
    }
}
