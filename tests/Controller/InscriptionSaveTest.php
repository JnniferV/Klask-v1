<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\Support\FunctionalTestCase;
use Symfony\Component\DomCrawler\Form;

class InscriptionSaveTest extends FunctionalTestCase
{
    private const CODE = 'GRP0001';

    private int $groupId;
    private int $establishmentId;
    private string $niveau;

    protected function setUp(): void
    {
        parent::setUp();

        $group = $this->fixture->student()->getGroup();
        $group->setCode(self::CODE);
        $this->em()->flush();

        $this->groupId = (int) $group->getId();
        $this->establishmentId = (int) $group->getEstablishment()->getId();
        $this->niveau = (string) $group->getName();
    }

    private function countStudents(): int
    {
        return $this->em()->getRepository(User::class)->count([]);
    }

    /** @param array<string, string> $champs */
    private function formulaire(array $champs = []): Form
    {
        $form = $this->client->request('GET', '/inscription')
            ->selectButton("Valider l'inscription")
            ->form();

        $valeurs = $champs + [
            'establishment' => (string) $this->establishmentId,
            'groupLevel' => $this->niveau,
            'groupCode' => self::CODE,
        ];

        foreach ($valeurs as $champ => $valeur) {
            $form['inscription_form['.$champ.']'] = $valeur;
        }

        return $form;
    }

    public function testUneInscriptionValideCreeLEleveLeRattacheEtLeConnecte(): void
    {
        $avant = $this->countStudents();

        $this->client->submit($this->formulaire());

        $this->assertResponseRedirects('/bienvenue');
        $this->assertSame($avant + 1, $this->countStudents());

        // auto-login requis
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        $inscrit = $this->em()->getRepository(User::class)->findOneBy([], ['id' => 'DESC']);
        $this->assertSame($this->groupId, $inscrit->getGroup()?->getId());
        $this->assertNotNull($inscrit->getPseudo(), 'Le pseudo tiré au sort doit être persisté.');
    }

    public function testUnCodeDeGroupeAuMauvaisFormatEstRefuse(): void
    {
        $avant = $this->countStudents();

        $this->client->submit($this->formulaire(['groupCode' => 'ABC']));

        $this->assertResponseIsSuccessful('Le formulaire est réaffiché avec l\'erreur.');
        $this->assertSame($avant, $this->countStudents());
    }

    public function testUnCodeDeGroupeInconnuEstRefuse(): void
    {
        $avant = $this->countStudents();

        $this->client->submit($this->formulaire(['groupCode' => 'ZZZ9999']));

        $this->assertResponseIsSuccessful();
        $this->assertSame($avant, $this->countStudents());
        $this->assertSelectorExists('.resultat');
    }

    public function testUnEtablissementQuiNeCorrespondPasAuGroupeEstRefuse(): void
    {
        $autreId = (int) $this->fixture->student()->getGroup()->getEstablishment()->getId();
        $avant = $this->countStudents();

        $this->client->submit($this->formulaire(['establishment' => (string) $autreId]));

        $this->assertResponseIsSuccessful();
        $this->assertSame($avant, $this->countStudents());
    }

    public function testUnFormulaireSansJetonCsrfNEnregistreRien(): void
    {
        $avant = $this->countStudents();

        $this->client->request('POST', '/inscription/save', ['inscription_form' => [
            'establishment' => (string) $this->establishmentId,
            'groupLevel' => $this->niveau,
            'groupCode' => self::CODE,
        ]]);

        $this->assertResponseIsSuccessful();
        $this->assertSame($avant, $this->countStudents());
    }

    // QR avant inscription
    public function testLeQrScanneAvantLInscriptionEstCrediteJusteApres(): void
    {
        $this->fixture->stand('token-avant-inscription', 50);
        $this->client->request('GET', '/scan/qr/token-avant-inscription');

        $this->client->submit($this->formulaire());

        $inscrit = $this->em()->getRepository(User::class)->findOneBy([], ['id' => 'DESC']);
        $this->assertGreaterThanOrEqual(50, $inscrit->getScore());
    }

    public function testUnEleveDejaConnecteNePeutPasSeReinscrire(): void
    {
        $this->client->loginUser($this->fixture->student(rated: true));
        $avant = $this->countStudents();

        $this->client->request('POST', '/inscription/save');

        $this->assertResponseRedirects('/map');
        $this->assertSame($avant, $this->countStudents());
    }
}
