<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Redirige si déjà connecté
        if ($this->getUser()) {
            return $this->redirectToRoute('app_map');
        }

        return $this->render('login/login.html.twig', [
            'error'      => $authenticationUtils->getLastAuthenticationError(),
            'last_email' => $authenticationUtils->getLastUsername(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        // Intercepté automatiquement par Symfony Security — ce code n'est jamais exécuté normalement
        throw new \LogicException('Cette méthode ne doit jamais être appelée directement.');
    }
}
