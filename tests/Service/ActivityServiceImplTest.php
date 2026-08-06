<?php

namespace App\Tests\Service;

use App\Service\Impl\ActivityServiceImpl;
use App\Tests\Support\EntityBuilder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ActivityServiceImplTest extends TestCase
{
    private function service(?EntityManagerInterface $em = null): ActivityServiceImpl
    {
        return new ActivityServiceImpl($em ?? $this->createMock(EntityManagerInterface::class));
    }

    public function testUnTokenQrEstGenereQuandLActiviteNEnAPasEncore(): void
    {
        $activity = EntityBuilder::activity(1, EntityBuilder::category());

        $this->service()->initQrCode($activity);

        $this->assertNotNull($activity->getQrcodeToken());
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
