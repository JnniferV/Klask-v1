<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ParcoursService;
use App\Service\Questionnaire;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class QuestionnaireController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly ParcoursService $parcoursService,
        private readonly Questionnaire $questionnaire,
        private readonly CsrfTokenManagerInterface $csrf,
    ) {
    }

    #[Route('/questionnaire', name: 'app_questionnaire', methods: ['GET'])]
    public function show(): Response
    {
        $student = $this->getUser();
        if ($student instanceof User && $this->userService->hasCompletedQuestionnaire($student)) {
            return $this->redirectToRoute('app_map');
        }

        $response = $this->render('questionnaire/questionnaire.html.twig', [
            'affirmations' => Questionnaire::AFFIRMATIONS,
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/questionnaire/save', name: 'app_questionnaire_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        if (!$this->csrf->isTokenValid(new CsrfToken('questionnaire', $request->request->getString('_csrf_token')))) {
            $this->addFlash('error', 'Token de sécurité invalide. Rechargez la page et réessayez.');

            return $this->redirectToRoute('app_questionnaire');
        }

        $student = $this->getUser();
        if (!$student instanceof User) {
            return $this->redirectToRoute('app_inscription_show');
        }

        if ($this->userService->hasCompletedQuestionnaire($student)) {
            return $this->redirectToRoute('app_map');
        }

        try {
            $zoneRatings = $this->questionnaire->toZoneRatings($request->request->all('ratings'));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_questionnaire');
        }

        // saveRatings persiste sans flush et retourne les ratings triés
        // generateForUser les utilise directement
        $sortedRatings = $this->userService->saveRatings($student, $zoneRatings);
        $this->parcoursService->generateForUser($student, $sortedRatings);

        return $this->redirectToRoute('app_map');
    }
}
