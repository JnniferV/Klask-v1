<?php

namespace App\Tests\Twig;

use App\Twig\AvatarExtension;
use PHPUnit\Framework\TestCase;

class AvatarExtensionTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/klask-avatars-'.uniqid();
        mkdir($this->dir.'/public/'.AvatarExtension::DIR, 0777, true);
    }

    protected function tearDown(): void
    {
        $base = $this->dir.'/public/'.AvatarExtension::DIR;
        foreach (glob($base.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($base);
        rmdir($this->dir.'/public');
        rmdir($this->dir);
    }

    private function withFiles(string ...$names): AvatarExtension
    {
        foreach ($names as $name) {
            touch($this->dir.'/public/'.AvatarExtension::DIR.'/'.$name);
        }

        return new AvatarExtension($this->dir);
    }

    public function testLeSlugRetireAccentsEspacesEtTirets(): void
    {
        $attendus = [
            'Coccinelle magique' => 'coccinellemagique',
            'Mérou cosmique' => 'meroucosmique',
            'Dauphin du rêve' => 'dauphindureve',
            'Renard à lunettes' => 'renardalunettes',
            'Phoque héroïque' => 'phoqueheroique',
            'Crabe casse-cou' => 'crabecassecou',
            'CRAPAUD MAGIQUE' => 'crapaudmagique',
        ];

        foreach ($attendus as $pseudo => $attendu) {
            $this->assertSame($attendu, AvatarExtension::slug($pseudo), $pseudo);
        }
    }

    // neutralise path traversal
    public function testLeSlugNeutraliseTouteTentativeDeTraverseeDeChemin(): void
    {
        $this->assertSame('etcpasswd', AvatarExtension::slug('../../etc/passwd'));
        $this->assertSame('', AvatarExtension::slug('../'));
    }

    public function testLeVisuelDuPseudoEstServiQuandLeFichierExiste(): void
    {
        $extension = $this->withFiles('coccinellemagique.webp', 'petitvaisseaucoccinellemagique.webp');

        $this->assertSame('avatars/coccinellemagique.webp', $extension->avatar('Coccinelle magique'));
        $this->assertSame(
            'avatars/petitvaisseaucoccinellemagique.webp',
            $extension->avatar('Coccinelle magique', 'petitvaisseau')
        );
    }

    public function testUnVisuelNonLivreRetombeSurLeRepliDeLaMemeTaille(): void
    {
        $extension = $this->withFiles('default.webp', 'vaisseaudefault.webp');

        $this->assertSame('avatars/default.webp', $extension->avatar('Dauphin du rêve'));
        $this->assertSame('avatars/vaisseaudefault.webp', $extension->avatar('Dauphin du rêve', 'vaisseau'));
    }

    public function testUnPseudoAbsentRetombeSurLeRepli(): void
    {
        $this->assertSame('avatars/vaisseaudefault.webp', $this->withFiles()->avatar(null, 'vaisseau'));
    }

    public function testLesTroisVariantesCouvrentLesTroisTaillesAffichees(): void
    {
        $this->assertSame(['', 'vaisseau', 'petitvaisseau'], array_keys(AvatarExtension::VARIANTS));
    }
}
