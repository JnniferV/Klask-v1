<?php

namespace App\Tests\Controller;

use App\Tests\Support\FunctionalTestCase;

class AdminStatsControllerTest extends FunctionalTestCase
{
    public function testLaPageStatsEstReserveeAuxAdmins(): void
    {
        $this->client->request('GET', '/admin/stats');

        $this->assertResponseRedirects();
        $this->assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testUnAdminVoitLaPageStatistiques(): void
    {
        $admin = $this->fixture->user('ADMIN');
        $admin->setEmail('admin-stats@test.fr');
        $this->em()->flush();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin/stats');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Statistiques', (string) $this->client->getResponse()->getContent());
    }
}
