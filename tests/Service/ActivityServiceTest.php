<?php

namespace App\Tests\Service;

use App\Service\ActivityService;
use App\Tests\Support\EntityBuilder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ActivityServiceTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/klask-activity-'.uniqid();
        mkdir($this->projectDir.'/public/'.ActivityService::QRCODE_DIR, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->projectDir.'/public/'.ActivityService::QRCODE_DIR.'/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->projectDir.'/public/'.ActivityService::QRCODE_DIR);
        @rmdir($this->projectDir.'/public');
        @rmdir($this->projectDir);
    }

    private function service(?EntityManagerInterface $em = null): ActivityService
    {
        return new ActivityService(
            $em ?? $this->createStub(EntityManagerInterface::class),
            $this->projectDir,
            'https://test.local',
        );
    }

    public function testUnTokenQrEstGenereQuandLActiviteNEnAPasEncore(): void
    {
        $activity = EntityBuilder::activity(1, EntityBuilder::category(), EntityBuilder::sphere(1, 'NOUVEAUTÉ'));

        $this->service()->initQrCode($activity);

        $this->assertNotNull($activity->getQrcodeToken());
        $this->assertNotNull($activity->getQrcode());
        $this->assertFileExists($this->projectDir.'/public/'.$activity->getQrcode());
    }

    public function testUnTokenQrExistantNEstJamaisRegenere(): void
    {
        $activity = EntityBuilder::activity(1, EntityBuilder::category())->setQrcodeToken('deja-imprime');

        $this->service()->initQrCode($activity);

        $this->assertSame('deja-imprime', $activity->getQrcodeToken(), 'Le QR physique déjà distribué doit rester valide.');
    }

    public function testLActiviteCreeeDepuisLaCarteEstPositionneeScannableEtPersistee(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $activity = $this->service($em)->createFromMap(
            EntityBuilder::sphere(1),
            EntityBuilder::category(),
            'Menuiserie',
            'Découverte du bois',
            12.5,
            30.0,
        );

        $this->assertSame('Menuiserie', $activity->getName());
        $this->assertSame(12.5, $activity->getPointX());
        $this->assertSame(30.0, $activity->getPointY());
        $this->assertNotNull($activity->getQrcodeToken());
    }
}
