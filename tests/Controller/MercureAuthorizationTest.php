<?php

namespace App\Tests\Controller;

use App\Tests\Support\FunctionalTestCase;

class MercureAuthorizationTest extends FunctionalTestCase
{
    /** @return string[] */
    private function topicsSignesSurLaCarte(): array
    {
        $this->client->request('GET', '/map');
        $this->assertResponseIsSuccessful();

        // lire headers pas CookieJar
        $cookie = null;
        foreach ($this->client->getResponse()->headers->getCookies() as $candidat) {
            if ('mercureAuthorization' === $candidat->getName()) {
                $cookie = $candidat;
            }
        }

        $this->assertNotNull($cookie, 'La carte doit poser le cookie d\'abonné Mercure.');
        $this->assertTrue($cookie->isHttpOnly(), 'Le jeton d\'abonné ne doit jamais être lisible en JS.');

        $payload = json_decode(base64_decode(strtr(explode('.', (string) $cookie->getValue())[1], '-_', '+/')), true);

        return $payload['mercure']['subscribe'];
    }

    public function testUnEleveNEstAbonneQuAuxTopicsDeSonGroupeEtDeLuiMeme(): void
    {
        $eleve = $this->fixture->student(rated: true);
        $code = (string) $eleve->getGroup()->getCode();
        $id = (int) $eleve->getId();
        $this->client->loginUser($eleve);

        $topics = $this->topicsSignesSurLaCarte();

        $this->assertContains('poke/'.$id, $topics);
        $this->assertContains('event-alert/class/'.$code, $topics);
        $this->assertContains('group-total/'.$code, $topics);
    }

    // group-score accompagnateur only
    public function testUnEleveNEstJamaisAbonneAuCanalDeLAccompagnateur(): void
    {
        $eleve = $this->fixture->student(rated: true);
        $code = (string) $eleve->getGroup()->getCode();
        $this->client->loginUser($eleve);

        $topics = $this->topicsSignesSurLaCarte();

        $this->assertNotContains('group-score/'.$code, $topics);
        $this->assertNotContains('event-alert/accompagnateur', $topics);
    }

    public function testUnEleveNEstAbonneAAucunTopicDUneAutreClasse(): void
    {
        $codeAutre = (string) $this->fixture->student()->getGroup()->getCode();
        $eleve = $this->fixture->student(rated: true);
        $this->client->loginUser($eleve);

        foreach ($this->topicsSignesSurLaCarte() as $topic) {
            $this->assertStringNotContainsString($codeAutre, $topic, 'Fuite vers une autre classe : '.$topic);
        }
    }

    public function testUnAccompagnateurEstAbonneAuxScoresDeSonGroupeEtPasAuxPokes(): void
    {
        $accompagnateur = $this->fixture->user('ACCOMPANYING');
        $accompagnateur->setEmail('mercure-accomp@test.fr');
        $this->em()->flush();
        $code = (string) $accompagnateur->getGroup()->getCode();
        $id = (int) $accompagnateur->getId();
        $this->client->loginUser($accompagnateur);

        $topics = $this->topicsSignesSurLaCarte();

        $this->assertContains('group-score/'.$code, $topics);
        $this->assertContains('event-alert/accompagnateur', $topics);
        $this->assertNotContains('poke/'.$id, $topics);
    }
}
