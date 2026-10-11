<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\Support\FunctionalTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

// scénario jour J : accompagnateur créé ou rattaché après l'inscription des élèves
class AccompanyingCrudTest extends FunctionalTestCase
{
    private const PASSWORD = 'Temporaire2026!';

    protected function setUp(): void
    {
        parent::setUp();

        // autorité ACCOMPANYING attendue par createEntity()
        $this->fixture->user('ACCOMPANYING');
        $admin = $this->fixture->user('ADMIN')->setEmail('admin-accomp@test.fr');
        $this->em()->flush();
        $this->client->loginUser($admin);
    }

    /** @param array<string, string> $champs */
    private function creer(array $champs): void
    {
        $form = $this->client->request('GET', '/admin/accompanying/new')->filter('#new-User-form')->form();
        $this->client->submit($form, $champs);
    }

    private function accompagnateur(string $email): ?User
    {
        $this->em()->clear();

        return $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function testUnAccompagnateurCreeApresLesElevesSeConnecteEtVoitSonGroupe(): void
    {
        $eleve = $this->fixture->student();
        $group = $eleve->getGroup();

        $this->creer([
            'User[pseudo]' => 'Mme Dernière Minute',
            'User[email]' => 'derniere-minute@test.fr',
            'User[group]' => (string) $group->getId(),
            'User[password]' => self::PASSWORD,
        ]);

        $this->assertResponseRedirects();
        $accompagnateur = $this->accompagnateur('derniere-minute@test.fr');
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->assertTrue($hasher->isPasswordValid($accompagnateur, self::PASSWORD), 'Le mot de passe envoyé par mail doit permettre la connexion (pas de double hachage).');

        $this->assertEmailCount(1);
        $this->assertEmailTextBodyContains($this->getMailerMessage(), $group->getCode());

        $this->client->loginUser($accompagnateur);
        $this->client->request('GET', '/map');
        $this->assertSelectorTextContains('.student-pseudo', (string) $eleve->getPseudo());
    }

    public function testUnEmailInvalideNeCreeAucunCompte(): void
    {
        $this->creer([
            'User[email]' => 'pas-un-email',
            'User[password]' => self::PASSWORD,
        ]);

        $this->assertNull($this->accompagnateur('pas-un-email'));
        $this->assertEmailCount(0);
    }

    public function testUnRattachementTardifPrevientSansToucherAuMotDePasse(): void
    {
        // élèves déjà inscrits, accompagnateur créé sans groupe
        $group = $this->fixture->student()->getGroup();
        $this->creer(['User[email]' => 'tardif@test.fr', 'User[password]' => self::PASSWORD]);
        $id = $this->accompagnateur('tardif@test.fr')->getId();

        $form = $this->client->request('GET', '/admin/accompanying/'.$id.'/edit')->filter('#edit-User-form')->form();
        $this->client->submit($form, ['User[group]' => (string) $group->getId()]);

        $this->assertResponseRedirects();
        $this->assertEmailCount(1);
        $this->assertEmailTextBodyContains($this->getMailerMessage(), $group->getCode());
        $this->assertEmailTextBodyNotContains($this->getMailerMessage(), 'Mot de passe');

        $accompagnateur = $this->accompagnateur('tardif@test.fr');
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->assertTrue($hasher->isPasswordValid($accompagnateur, self::PASSWORD));
        $this->assertSame($group->getId(), $accompagnateur->getGroup()?->getId());
    }
}
