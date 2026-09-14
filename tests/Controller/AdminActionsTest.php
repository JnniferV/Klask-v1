<?php

namespace App\Tests\Controller;

use App\Entity\AppParameter;
use App\Entity\Sphere;
use App\Repository\AppParameterRepository;
use App\Tests\Support\FunctionalTestCase;

class AdminActionsTest extends FunctionalTestCase
{
    private function admin(string $email): object
    {
        $admin = $this->fixture->user('ADMIN');
        $admin->setEmail($email);
        $this->em()->flush();

        return $admin;
    }

    private function parametre(string $key, string $value, string $type): AppParameter
    {
        $param = (new AppParameter())->setParamKey($key)->setParamValue($value)->setParamType($type);
        $this->em()->persist($param);
        $this->em()->flush();

        return $param;
    }

    private function relire(string $key): AppParameter
    {
        return static::getContainer()->get(AppParameterRepository::class)->findOneBy(['paramKey' => $key]);
    }

    private function csrfTokenFromPage(string $url, string $selector): string
    {
        $this->client->request('GET', $url);

        return (string) $this->client->getCrawler()->filter($selector)->attr('value');
    }

    public function testUnAdminModifieUnSeuilDeScan(): void
    {
        $this->parametre('SCAN_DELAY_MINUTES', '5', 'integer');
        $this->client->loginUser($this->admin('admin-params@test.fr'));

        $this->client->request('POST', '/admin/parameters/save', [
            '_token' => $this->csrfTokenFromPage('/admin', '#params-form input[name="_token"]'),
            'params' => ['SCAN_DELAY_MINUTES' => '12'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame('12', $this->relire('SCAN_DELAY_MINUTES')->getParamValue());
    }

    // case décochée absente
    public function testUneCaseDecocheeRepasseLeBooleenAFalse(): void
    {
        $this->parametre('ALERT_MAP_ACTIVE', 'true', 'boolean');
        $this->client->loginUser($this->admin('admin-alerte@test.fr'));

        $this->client->request('POST', '/admin/parameters/save', [
            '_token' => $this->csrfTokenFromPage('/admin', '#params-form input[name="_token"]'),
            'params' => [],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame('false', $this->relire('ALERT_MAP_ACTIVE')->getParamValue());
    }

    // clé hors PARAM_GROUPS
    public function testUneCleHorsTableauDeBordNEstPasModifiable(): void
    {
        $this->parametre('CLE_HORS_PERIMETRE', 'origine', 'string');
        $this->client->loginUser($this->admin('admin-hors@test.fr'));

        $this->client->request('POST', '/admin/parameters/save', [
            '_token' => $this->csrfTokenFromPage('/admin', '#params-form input[name="_token"]'),
            'params' => ['CLE_HORS_PERIMETRE' => 'pirate'],
        ]);

        $this->assertSame('origine', $this->relire('CLE_HORS_PERIMETRE')->getParamValue());
    }

    public function testLesActionsDAdministrationSontFermeesAuxEleves(): void
    {
        $this->client->loginUser($this->fixture->student(rated: true));

        $this->client->request('POST', '/admin/parameters/save', ['params' => []]);
        $this->assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/admin/placement/sphere/1');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testUnAdminDeplaceUneSphereSurLaCarte(): void
    {
        $sphereId = (int) $this->fixture->sphere()->getId();
        $this->client->loginUser($this->admin('admin-placement@test.fr'));

        $this->client->request('POST', '/admin/placement/sphere/'.$sphereId, [
            '_token' => $this->csrfTokenFromPage('/admin/placement/sphere/'.$sphereId, '.placement-form input[name="_token"]'),
            'pointX' => '42.5',
            'pointY' => '17.5',
            'radius' => '9',
        ]);

        $this->assertResponseRedirects('/admin/placement/sphere/'.$sphereId);

        // kernel redémarré
        $relue = $this->em()->getRepository(Sphere::class)->find($sphereId);
        $this->assertSame(42.5, $relue->getPointX());
        $this->assertSame(17.5, $relue->getPointY());
    }

    public function testUnPointInexistantRenvoieUne404(): void
    {
        $this->client->loginUser($this->admin('admin-404@test.fr'));

        $this->client->request('GET', '/admin/placement/activity/999999');

        $this->assertResponseStatusCodeSame(404);
    }
}
