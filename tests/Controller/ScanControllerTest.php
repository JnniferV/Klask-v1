<?php

namespace App\Tests\Controller;

use App\Controller\ScanController;
use App\Entity\User;
use App\Tests\Support\FunctionalTestCase;

class ScanControllerTest extends FunctionalTestCase
{
    private const TOKEN = 'token-qr-de-test';

    public function testLeScanApplicatifExigeUnJetonCsrf(): void
    {
        $this->client->loginUser($this->fixture->student(rated: true));
        $this->client->request('POST', '/scan', ['token' => self::TOKEN]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testLeScanApplicatifEstFermeAuxVisiteurs(): void
    {
        $this->client->request('POST', '/scan', ['token' => self::TOKEN]);

        $this->assertResponseRedirects();
        $this->assertStringEndsWith('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testUnQrScanneAvantInscriptionEstMemoriseEnSession(): void
    {
        $this->fixture->stand(self::TOKEN);
        $this->client->request('GET', '/scan/qr/'.self::TOKEN);

        $this->assertResponseIsSuccessful();
        $this->assertSame(
            self::TOKEN,
            $this->client->getRequest()->getSession()->get(ScanController::SESSION_PENDING_SCAN),
            'Le stand doit être crédité juste après l\'inscription.'
        );
    }

    public function testUnQrScanneParUnEleveCrediteSesPoints(): void
    {
        $student = $this->fixture->student(rated: true);
        $id = (int) $student->getId();
        $this->fixture->stand(self::TOKEN, 50);
        $this->client->loginUser($student);

        $this->client->request('GET', '/scan/qr/'.self::TOKEN);

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThanOrEqual(50, $this->em()->find(User::class, $id)->getScore());
    }
}
