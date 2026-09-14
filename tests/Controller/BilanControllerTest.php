<?php

namespace App\Tests\Controller;

use App\Tests\Support\FunctionalTestCase;

class BilanControllerTest extends FunctionalTestCase
{
    public function testLeBilanEleveEstReserveAuxElevesConnectes(): void
    {
        $this->client->request('GET', '/bilan/eleve');

        $this->assertResponseRedirects();
        $this->assertStringEndsWith('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testUnEleveVoitSonBilan(): void
    {
        $student = $this->fixture->student(rated: true);
        $stand = $this->fixture->stand('token-bilan-1');
        $this->fixture->scan($student, $stand);
        $student->setScore(50);
        $this->em()->flush();

        $this->client->loginUser($student);
        $this->client->request('GET', '/bilan/eleve');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mon bilan');
        $this->assertSelectorTextContains('.welcome-score', '50');
    }

    public function testLePdfEleveResteVerrouilleSansAssezDeScans(): void
    {
        $student = $this->fixture->student(rated: true);
        $this->client->loginUser($student);

        $this->client->request('GET', '/bilan/eleve/pdf');

        $this->assertResponseRedirects('/bilan/eleve');
    }

    public function testLeBilanGroupeEstReserveAuxAccompagnateurs(): void
    {
        $student = $this->fixture->student(rated: true);
        $this->client->loginUser($student);

        $this->client->request('GET', '/bilan/groupe');

        $this->assertResponseStatusCodeSame(403);
    }

    private function accompagnateurAvecScans(string $email, int $scans): object
    {
        $accompagnateur = $this->fixture->user('ACCOMPANYING');
        $accompagnateur->setEmail($email);

        $eleve = $this->fixture->student();
        $eleve->setGroup($accompagnateur->getGroup());
        for ($i = 0; $i < $scans; ++$i) {
            $this->fixture->scan($eleve, $this->fixture->stand($email.'-'.$i));
        }
        $this->em()->flush();

        return $accompagnateur;
    }

    public function testLePdfDeGroupeResteVerrouilleSousTroisScansDeLaClasse(): void
    {
        $this->client->loginUser($this->accompagnateurAvecScans('pdf-verrou@test.fr', 2));

        $this->client->request('GET', '/bilan/groupe/pdf');

        $this->assertResponseRedirects('/bilan/groupe');
    }

    public function testUnAccompagnateurTelechargeLeBilanPdfDeSonGroupe(): void
    {
        $this->client->loginUser($this->accompagnateurAvecScans('pdf-ok@test.fr', 3));

        $this->client->request('GET', '/bilan/groupe/pdf');

        $this->assertResponseIsSuccessful();
        $this->assertSame('application/pdf', $this->client->getResponse()->headers->get('Content-Type'));
    }

    public function testLePdfDeGroupeEstFermeAuxEleves(): void
    {
        $this->client->loginUser($this->fixture->student(rated: true));

        $this->client->request('GET', '/bilan/groupe/pdf');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testUnAccompagnateurVoitLeBilanDeSonGroupe(): void
    {
        $accompanying = $this->fixture->user('ACCOMPANYING');
        $accompanying->setEmail('accomp@test.fr');
        $this->em()->flush();

        $this->client->loginUser($accompanying);
        $this->client->request('GET', '/bilan/groupe');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Bilan groupe');
    }
}
