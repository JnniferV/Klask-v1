<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    // `/` n'a pas de contenu propre : on aiguille sans rendre de page intermédiaire
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute($this->getUser() ? 'app_map' : 'app_inscription_show');
    }

    // page statique : aucune donnée à préparer, le template se suffit
    #[Route('/mentions-legales', name: 'app_legal', methods: ['GET'])]
    public function legal(): Response
    {
        return $this->render('legal/mentions.html.twig');
    }
}
