<?php

namespace App\Tests\Service;

use App\Entity\ActivityCategory;
use App\Service\SphereBoundsCalculator;
use App\Tests\Support\EntityBuilder;
use PHPUnit\Framework\TestCase;


class SphereBoundsCalculatorTest extends TestCase
{
    public function testCentreEtRayonCalculesDepuisLesCoordonneesDesStands(): void
    {
        $bounds = SphereBoundsCalculator::fromActivities([
            ['pointXActivity' => 20.0, 'pointYActivity' => 30.0],
            ['pointXActivity' => 40.0, 'pointYActivity' => 50.0],
        ]);

        $this->assertSame(30.0, $bounds['centerX']);
        $this->assertSame(40.0, $bounds['centerY']);
        $this->assertSame(16.0, $bounds['radius'], 'Rayon = plus grand écart au centre (10) + marge de 6.');
    }

    public function testUnSeulStandDonneUnCercleReduitALaMarge(): void
    {
        $bounds = SphereBoundsCalculator::fromActivities([['pointXActivity' => 50.0, 'pointYActivity' => 50.0]]);

        $this->assertSame(50.0, $bounds['centerX']);
        $this->assertSame(6.0, $bounds['radius']);
    }

    public function testAucunStandDonneAucuneBounds(): void
    {
        $this->assertNull(SphereBoundsCalculator::fromActivities([]));
        $this->assertNull(SphereBoundsCalculator::fromStands([]));
    }

    public function testFromStandsConvertitLesEntitesActivity(): void
    {
        $category = EntityBuilder::category(ActivityCategory::TYPE_STAND);
        $a = EntityBuilder::activity(1, $category);
        $b = EntityBuilder::activity(2, $category);
        $a->setPointX(10.0)->setPointY(10.0);
        $b->setPointX(30.0)->setPointY(20.0);

        $bounds = SphereBoundsCalculator::fromStands([$a, $b]);

        $this->assertSame(20.0, $bounds['centerX']);
        $this->assertSame(15.0, $bounds['centerY']);
    }

    public function testDeuxSpheresQuiSeSuperposentSontDetectees(): void
    {
        $a = ['centerX' => 50.0, 'centerY' => 50.0, 'radius' => 10.0];
        $b = ['centerX' => 55.0, 'centerY' => 50.0, 'radius' => 10.0];

        $this->assertTrue(SphereBoundsCalculator::overlap($a, $b), 'Distance 5 < somme des rayons 20.');
    }

    public function testDeuxSpheresEloigneesNeSeSuperposentPas(): void
    {
        $a = ['centerX' => 10.0, 'centerY' => 10.0, 'radius' => 5.0];
        $b = ['centerX' => 90.0, 'centerY' => 90.0, 'radius' => 5.0];

        $this->assertFalse(SphereBoundsCalculator::overlap($a, $b));
    }
}
