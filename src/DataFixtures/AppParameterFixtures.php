<?php

namespace App\DataFixtures;

use App\Entity\AppParameter;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppParameterFixtures extends Fixture
{
    private const PARAMETERS = [
        [
            'key'         => 'BLOCK_DURATION_MINUTES',
            'value'       => '5',
            'type'        => 'integer',
            'description' => 'Durée (min) du blocage après INVALID_SCAN_THRESHOLD scans invalides consécutifs.',
        ],
        [
            'key'         => 'INVALID_SCAN_THRESHOLD',
            'value'       => '3',
            'type'        => 'integer',
            'description' => 'Nombre de scans invalides consécutifs avant blocage temporaire.',
        ],
        [
            'key'         => 'SCAN_DELAY_MINUTES',
            'value'       => '5',
            'type'        => 'integer',
            'description' => 'Délai minimum (min) entre deux scans d\'activités différentes par un même élève. Les quêtes sont exemptées.',
        ],
        [
            'key'         => 'SOFT_CAPACITY_MARGIN',
            'value'       => '80',
            'type'        => 'integer',
            'description' => '% de la hardLimit à partir duquel l\'indicateur passe à "Presque plein" (si softLimit non défini sur l\'activité).',
        ],
        [
            'key'         => 'ALERT_BEFORE_EVENT_MIN',
            'value'       => '10',
            'type'        => 'integer',
            'description' => 'Délai (min) avant l\'heure fixée d\'un atelier/conférence pour déclencher l\'alerte élève.',
        ],
        [
            'key'         => 'MAX_STUDENTS_PER_GROUP',
            'value'       => '40',
            'type'        => 'integer',
            'description' => 'Maximum d\'élèves dans un groupe de classe.',
        ],
        [
            'key'         => 'BONUS_TOP3_SPHERES',
            'value'       => '50',
            'type'        => 'integer',
            'description' => 'Bonus de points quand les 3 sphères préférées (étapes 1 à 3 du parcours) sont faites.',
        ],
        [
            'key'         => 'BONUS_ALL_SPHERES',
            'value'       => '100',
            'type'        => 'integer',
            'description' => 'Bonus de points quand les stands des 6 sphères sont faits.',
        ],
        [
            'key'         => 'BONUS_MAX_SCORE',
            'value'       => '150',
            'type'        => 'integer',
            'description' => 'Bonus de points du score maximal : toute la carte faite, ateliers et conférences compris.',
        ],
        [
            'key'         => 'ALERT_MAP_ACTIVE',
            'value'       => 'false',
            'type'        => 'boolean',
            'description' => 'Remplace la carte interactive par la carte évacuation/sorties de secours.',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::PARAMETERS as $data) {
            $param = new AppParameter();
            $param->setParamKey($data['key'])
                  ->setParamValue($data['value'])
                  ->setParamType($data['type'])
                  ->setDescription($data['description']);

            $manager->persist($param);
        }

        $manager->flush();
    }
}
