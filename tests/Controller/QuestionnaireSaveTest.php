<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserSphereRatingRepository;
use App\Tests\Support\FunctionalTestCase;
use Symfony\Component\DomCrawler\Form;

class QuestionnaireSaveTest extends FunctionalTestCase
{
    private const NOTES_VALIDES = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6];

    private const ZONES = ['CRÉATIF', 'RIGOUREUX', 'NOUVEAUTÉ', 'EXTÉRIEUR', 'COMMUNIQUER', 'UTILE'];

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (self::ZONES as $zone) {
            $this->fixture->sphere($zone);
        }

        $student = $this->fixture->student();
        $this->studentId = (int) $student->getId();
        $this->client->loginUser($student);
    }

    /** @param array<string, int|string> $notes */
    private function form(array $notes): Form
    {
        $form = $this->client->request('GET', '/questionnaire')
            ->selectButton('Valider mon parcours')
            ->form();

        foreach ($notes as $letter => $note) {
            $form['ratings['.$letter.']'] = (string) $note;
        }

        return $form;
    }

    private function countRatings(): int
    {
        $student = $this->em()->find(User::class, $this->studentId);

        return count(static::getContainer()->get(UserSphereRatingRepository::class)->findRatingsOrderedByScore($student));
    }

    public function testUnQuestionnaireCompletMeneALaCarteEtEnregistreLesSixNotes(): void
    {
        $this->client->submit($this->form(self::NOTES_VALIDES));

        $this->assertResponseRedirects('/map');
        $this->assertSame(6, $this->countRatings());
    }

    public function testUnRenvoiDuMemeFormulaireNeCreePasDeSecondParcours(): void
    {
        $form = $this->form(self::NOTES_VALIDES);
        $this->client->submit($form);
        $this->client->submit($form);

        $this->assertResponseRedirects('/map');
        $this->assertSame(6, $this->countRatings(), 'Le questionnaire ne se rejoue pas.');
    }

    public function testDeuxAffirmationsAvecLaMemeNoteSontRefusees(): void
    {
        $this->client->submit($this->form(['A' => 1, 'B' => 1, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6]));

        $this->assertResponseRedirects('/questionnaire');
        $this->assertSame(0, $this->countRatings());
    }

    public function testUneAffirmationNonNoteeEstRefusee(): void
    {
        $this->client->submit($this->form(['A' => '', 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6]));

        $this->assertResponseRedirects('/questionnaire');
        $this->assertSame(0, $this->countRatings());
    }

    public function testUnJetonCsrfInvalideBloqueLEnregistrement(): void
    {
        $this->client->request('POST', '/questionnaire/save', [
            '_csrf_token' => 'jeton-falsifie',
            'ratings' => self::NOTES_VALIDES,
        ]);

        $this->assertResponseRedirects('/questionnaire');
        $this->assertSame(0, $this->countRatings());
    }
}
