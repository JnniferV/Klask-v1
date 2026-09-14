<?php

namespace App\Tests\Service;

use App\Service\Questionnaire;
use PHPUnit\Framework\TestCase;

class QuestionnaireTest extends TestCase
{
    private const CLASSEMENT_VALIDE = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6];

    private Questionnaire $questionnaire;

    protected function setUp(): void
    {
        $this->questionnaire = new Questionnaire();
    }

    public function testUnClassementValideEstTraduitEnNotesParSphere(): void
    {
        $this->assertSame(
            ['CRÉATIF' => 1, 'RIGOUREUX' => 2, 'NOUVEAUTÉ' => 3, 'EXTÉRIEUR' => 4, 'COMMUNIQUER' => 5, 'UTILE' => 6],
            $this->questionnaire->toZoneRatings(self::CLASSEMENT_VALIDE)
        );
    }

    public function testDeuxAffirmationsNePeuventPasPartagerLeMemeChiffre(): void
    {
        $this->expectExceptionMessage('une seule fois');

        $this->questionnaire->toZoneRatings(['A' => 1, 'B' => 1, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6]);
    }

    public function testUneNoteHorsDeLIntervalleEstRefusee(): void
    {
        foreach ([0, 7, -1] as $note) {
            try {
                $this->questionnaire->toZoneRatings(['A' => $note] + self::CLASSEMENT_VALIDE);
                $this->fail("La note {$note} aurait dû être refusée.");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('entre 1 et 6', $e->getMessage());
            }
        }
    }

    // valeur absente = 0
    public function testUneAffirmationAbsenteOuNonNumeriqueEstRefusee(): void
    {
        $sansA = self::CLASSEMENT_VALIDE;
        unset($sansA['A']);

        $this->expectException(\InvalidArgumentException::class);

        $this->questionnaire->toZoneRatings($sansA);
    }
}
