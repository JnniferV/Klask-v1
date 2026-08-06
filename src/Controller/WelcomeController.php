<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WelcomeController extends AbstractController
{
    public function __construct(private readonly UserService $userService) {}

    #[Route('/bienvenue', name: 'app_bienvenue', methods: ['GET'])]
    public function index(): Response
    {
        $student = $this->getUser();
        if (!$student instanceof User) {
            return $this->redirectToRoute('app_inscription_show');
        }

        if ($this->userService->hasCompletedQuestionnaire($student)) {
            return $this->redirectToRoute('app_map');
        }

        $response = $this->render('bienvenue/bienvenue.html.twig', [
            'pseudo' => $student->getPseudo() ?? '',
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
