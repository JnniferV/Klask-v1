<?php

namespace App\Tests\Controller;

use App\Tests\Support\FunctionalTestCase;

class StudentLockTest extends FunctionalTestCase
{
    private function assertNoStore(): void
    {
        $this->assertStringContainsString(
            'no-store',
            (string) $this->client->getResponse()->headers->get('Cache-Control'),
            'Sans no-store, le bouton Retour réaffiche la page depuis le cache navigateur.'
        );
    }

    public function testUnEleveNoteNePeutPasRevenirAuQuestionnaire(): void
    {
        $this->client->loginUser($this->fixture->student(rated: true));
        $this->client->request('GET', '/questionnaire');

        $this->assertResponseRedirects('/map');
    }

    public function testUnEleveNoteNePeutPasRevenirALaPageBienvenue(): void
    {
        $this->client->loginUser($this->fixture->student(rated: true));
        $this->client->request('GET', '/bienvenue');

        $this->assertResponseRedirects('/map');
    }

    public function testLeQuestionnaireNEstJamaisServiDepuisLeCacheNavigateur(): void
    {
        $this->client->loginUser($this->fixture->student());
        $this->client->request('GET', '/questionnaire');

        $this->assertResponseIsSuccessful();
        $this->assertNoStore();
    }

    public function testLaPageBienvenueNEstJamaisServieDepuisLeCacheNavigateur(): void
    {
        $this->client->loginUser($this->fixture->student());
        $this->client->request('GET', '/bienvenue');

        $this->assertResponseIsSuccessful();
        $this->assertNoStore();
    }

    public function testLeFormulaireDInscriptionNEstJamaisServiDepuisLeCacheNavigateur(): void
    {
        $this->client->request('GET', '/inscription');

        $this->assertResponseIsSuccessful();
        $this->assertNoStore();
    }

    public function testLaCarteRenvoieAuQuestionnaireTantQuIlNEstPasRempli(): void
    {
        $this->client->loginUser($this->fixture->student());
        $this->client->request('GET', '/map');

        $this->assertResponseRedirects('/questionnaire');
    }

    public function testUnEleveNoteAccedeALaCarte(): void
    {
        $this->client->loginUser($this->fixture->student(rated: true));
        $this->client->request('GET', '/map');

        $this->assertResponseIsSuccessful();
    }

    public function testLesPagesDEntreeRamenentUnEleveConnecteVersLaCarte(): void
    {
        $this->client->loginUser($this->fixture->student(rated: true));

        foreach (['/', '/login', '/inscription'] as $url) {
            $this->client->request('GET', $url);
            $this->assertResponseRedirects('/map', null, $url.' doit ramener vers la carte.');
        }
    }
}
