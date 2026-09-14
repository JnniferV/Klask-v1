<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\Support\FunctionalTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class ResetPasswordControllerTest extends FunctionalTestCase
{
    public function testLaPageMotDePasseOublieEstPublique(): void
    {
        $this->client->request('GET', '/reset-password');

        $this->assertResponseIsSuccessful();
    }

    public function testLaDemandeRedirigeVersCheckEmailSansRevelerSiLeCompteExiste(): void
    {
        $this->client->request('GET', '/reset-password');
        $this->client->submitForm('Envoyer le lien', [
            'reset_password_request_form[email]' => 'inconnu@klask.fr',
        ]);

        $this->assertResponseRedirects('/reset-password/check-email');
    }

    public function testLaPageCheckEmailEstPublique(): void
    {
        $this->client->request('GET', '/reset-password/check-email');

        $this->assertResponseIsSuccessful();
    }

    public function testUnStaffPeutReinitialiserSonMotDePasse(): void
    {
        $user = $this->fixture->user('ADMIN');
        $user->setEmail('staff-reset@test.fr');
        $this->em()->flush();

        $helper = static::getContainer()->get(ResetPasswordHelperInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $token = $helper->generateResetToken($user)->getToken();

        $this->client->request('GET', '/reset-password/reset/'.$token);
        $this->client->followRedirect();

        $this->client->submitForm('Enregistrer le mot de passe', [
            'change_password_form[plainPassword][first]' => 'NewPassword2026!',
            'change_password_form[plainPassword][second]' => 'NewPassword2026!',
        ]);

        $this->assertResponseRedirects('/login');

        $refreshed = $this->em()->find(User::class, $user->getId());
        $this->assertTrue($hasher->isPasswordValid($refreshed, 'NewPassword2026!'));
    }
}
