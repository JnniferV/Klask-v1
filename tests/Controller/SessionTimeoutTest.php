<?php

namespace App\Tests\Controller;

use App\Tests\Support\FunctionalTestCase;

class SessionTimeoutTest extends FunctionalTestCase
{
    public function testUnAccompagnateurResteConnecteApresUneHeureSansRequete(): void
    {
        $accompagnateur = $this->fixture->user('ACCOMPANYING');
        $accompagnateur->setEmail('accomp-timeout@test.fr');
        $this->em()->flush();

        $this->client->loginUser($accompagnateur);
        $this->client->request('GET', '/map');

        $session = $this->client->getRequest()->getSession();
        $session->set('klask_last_activity', time() - 3600);
        $session->save();

        $this->client->request('GET', '/map');

        $this->assertResponseIsSuccessful();
    }

    public function testUnAdminInactifEstDeconnecte(): void
    {
        $admin = $this->fixture->user('ADMIN');
        $admin->setEmail('admin-timeout@test.fr');
        $this->em()->flush();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin');

        $session = $this->client->getRequest()->getSession();
        $session->set('klask_last_activity', time() - 14401);
        $session->save();

        $this->client->request('GET', '/admin');

        $this->assertResponseRedirects();
        $this->assertStringContainsString('/logout', (string) $this->client->getResponse()->headers->get('Location'));
    }
}
