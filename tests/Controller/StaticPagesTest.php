<?php

namespace App\Tests\Controller;

use App\Tests\Support\FunctionalTestCase;

class StaticPagesTest extends FunctionalTestCase
{
    public function testLesMentionsLegalesSontPubliquesEtSAffichent(): void
    {
        $this->client->request('GET', '/mentions-legales');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mentions légales');
    }

    public function testLesMentionsLegalesSontAccessiblesDepuisLaSidebarDeLaCarte(): void
    {
        $this->client->loginUser($this->fixture->student(rated: true));
        $this->client->request('GET', '/map');

        $this->assertSelectorExists('a[href="/mentions-legales"]');
    }

    public function testUnEleveMarqueLesInstructionsCommeLues(): void
    {
        $this->client->loginUser($this->fixture->student(rated: true));

        $this->client->request('POST', '/map/intro/ack');

        $this->assertResponseIsSuccessful();
        $this->assertJsonStringEqualsJsonString('{"ok":true}', (string) $this->client->getResponse()->getContent());
    }

    public function testLAccuseDeLectureDesInstructionsEstFermeAuxVisiteurs(): void
    {
        $this->client->request('POST', '/map/intro/ack');

        $this->assertResponseRedirects();
        $this->assertStringEndsWith('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }
}
