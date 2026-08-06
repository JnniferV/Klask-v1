<?php

namespace App\Tests\Controller;

use App\Tests\Support\FunctionalTestCase;

class AccessControlTest extends FunctionalTestCase
{
    public function testLaPageDAccueilResteOuverteAuxVisiteurs(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
    }

    public function testLesPagesEleveSontFermeesAuxVisiteurs(): void
    {
        foreach (['/map', '/questionnaire', '/bienvenue'] as $url) {
            $this->client->request('GET', $url);

            $this->assertResponseRedirects(null, null, $url . ' doit exiger une connexion.');
            $this->assertStringEndsWith('/login', (string) $this->client->getResponse()->headers->get('Location'));
        }
    }

    public function testUnEleveNAccedeNiAuBackOfficeNiALEspaceAccompagnateur(): void
    {
        $this->client->loginUser($this->fixture->student(rated: true));

        $this->client->request('GET', '/admin');
        $this->assertResponseStatusCodeSame(403, 'Le back-office doit rester interdit à un élève.');

        $this->client->request('POST', '/accompanying/poke/1');
        $this->assertResponseStatusCodeSame(403, 'Le poke accompagnateur doit rester interdit à un élève.');
    }
}
