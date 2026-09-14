<?php

namespace App\Tests\Controller;

use App\Tests\Support\FunctionalTestCase;

class MapNotificationsTest extends FunctionalTestCase
{
    private const URL = '/map/notifications';

    /** @return array<int, array<string, mixed>> */
    private function replay(): array
    {
        $this->client->request('GET', self::URL);
        $this->assertResponseIsSuccessful();

        return json_decode((string) $this->client->getResponse()->getContent(), true);
    }

    public function testLeRejeuEstFermeAuxVisiteurs(): void
    {
        $this->client->request('GET', self::URL);

        $this->assertResponseRedirects();
        $this->assertStringEndsWith('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testUnEleveRecoitLesNotificationsQuiLeVisent(): void
    {
        $this->client->loginUser($this->fixture->student(rated: true));
        $envoyee = $this->fixture->sentNotification('student');

        $payloads = $this->replay();

        $this->assertSame([$envoyee->getId()], array_column($payloads, 'id'));
        $this->assertSame($envoyee->getMessage(), $payloads[0]['message']);
    }

    public function testUnEleveNeRecoitPasLesNotificationsDUneAutreClasse(): void
    {
        $this->client->loginUser($this->fixture->student(rated: true));
        $this->fixture->sentNotification('class', 'GRP-AUTRE-CLASSE');
        $this->fixture->sentNotification('accompagnateur');

        $this->assertSame([], $this->replay());
    }

    public function testUneNotificationJamaisEnvoyeeNEstPasRejouee(): void
    {
        $this->client->loginUser($this->fixture->student(rated: true));
        $this->fixture->sentNotification('student')->setSentAt(null);
        $this->em()->flush();

        $this->assertSame([], $this->replay());
    }

    // no-store requis
    public function testLeRejeuNEstJamaisMisEnCache(): void
    {
        $this->client->loginUser($this->fixture->student(rated: true));
        $this->client->request('GET', self::URL);

        $this->assertStringContainsString(
            'no-store',
            (string) $this->client->getResponse()->headers->get('Cache-Control')
        );
    }
}
