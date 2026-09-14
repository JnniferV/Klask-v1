<?php

namespace App\DataFixtures;

use App\Entity\ActivityCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ActivityCategoryFixtures extends Fixture
{
    public const CATEGORY_STAND_REFERENCE = 'category_stand';
    public const CATEGORY_ATELIER_REFERENCE = 'category_atelier';
    public const CATEGORY_CONFERENCE_REFERENCE = 'category_conference';
    public const CATEGORY_PROFESSION_REFERENCE = 'category_profession';

    public function load(ObjectManager $manager): void
    {
        // stand sphère — 50 pts si sphère dans le top 3, 25 sinon (cf. ScanService::resolvePoints)
        $stand = new ActivityCategory();
        $stand->setType(ActivityCategory::TYPE_STAND);
        $stand->setNbrPoints(50);
        $manager->persist($stand);
        $this->addReference(self::CATEGORY_STAND_REFERENCE, $stand);

        // atelier « qui est-ce ? » — 15 pts, horaire défini en dernier moment par l'admin
        $atelier = new ActivityCategory();
        $atelier->setType(ActivityCategory::TYPE_ATELIER);
        $atelier->setNbrPoints(15);
        $manager->persist($atelier);
        $this->addReference(self::CATEGORY_ATELIER_REFERENCE, $atelier);

        // conférence — 15 pts, horaire défini en dernier moment par l'admin
        $conference = new ActivityCategory();
        $conference->setType(ActivityCategory::TYPE_CONFERENCE);
        $conference->setNbrPoints(15);
        $manager->persist($conference);
        $this->addReference(self::CATEGORY_CONFERENCE_REFERENCE, $conference);

        // profession — rencontre métier, points paramétrables via le CRUD catégories
        $profession = new ActivityCategory();
        $profession->setType(ActivityCategory::TYPE_PROFESSION);
        $profession->setNbrPoints(20);
        $manager->persist($profession);
        $this->addReference(self::CATEGORY_PROFESSION_REFERENCE, $profession);

        $manager->flush();
    }
}
