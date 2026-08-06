<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ParcoursService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class QuestionnaireController extends AbstractController
{
    private const AFFIRMATIONS = [
        'A' => ['text' => "J'aime créer de mes mains : Je suis manuel, j'aime transformer la matière, cuisiner ou réparer.", 'zone' => 'CRÉATIF'],
        'B' => ['text' => "Je suis rigoureux : J'aime l'ordre, les règles, la précision et quand tout est bien organisé.", 'zone' => 'RIGOUREUX'],
        'C' => ['text' => "J'aime la nouveauté : Je suis curieux des technologies, du digital, de l'info et de l'innovation.", 'zone' => 'NOUVEAUTÉ'],
        'D' => ['text' => "J'aime être en extérieur : J'ai besoin de bouger, d'être dehors et au contact de la nature ou du terrain.", 'zone' => 'EXTÉRIEUR'],
        'E' => ['text' => "J'aime communiquer : J'aime parler, convaincre, expliquer des choses et rencontrer de nouvelles personnes.", 'zone' => 'COMMUNIQUER'],
        'F' => ['text' => "J'aime me sentir utile : J'ai le sens du service, j'aime soigner, aider et m'occuper des autres.", 'zone' => 'UTILE'],
    ];

    public function __construct(
        private readonly UserService $userService,
        private readonly ParcoursService $parcoursService,
        private readonly CsrfTokenManagerInterface $csrf,
    ) {}

    #[Route('/questionnaire', name: 'app_questionnaire', methods: ['GET'])]
    public function show(): Response
    {
        $student = $this->getUser();
        if ($student instanceof User && $this->userService->hasCompletedQuestionnaire($student)) {
            return $this->redirectToRoute('app_map');
        }

        $response = $this->render('questionnaire/questionnaire.html.twig', [
            'affirmations' => self::AFFIRMATIONS,
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/questionnaire/save', name: 'app_questionnaire_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        if (!$this->csrf->isTokenValid(new CsrfToken('questionnaire', $request->request->get('_csrf_token')))) {
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

        $ratings = $request->request->all('ratings');
        $letters = array_keys(self::AFFIRMATIONS);
        $values  = [];

        foreach ($letters as $letter) {
            $value = isset($ratings[$letter]) ? (int) $ratings[$letter] : 0;
            if ($value < 1 || $value > 6) {
                $this->addFlash('error', 'Chaque affirmation doit recevoir une note entre 1 et 6.');
                return $this->redirectToRoute('app_questionnaire');
            }
            $values[] = $value;
        }

        if (count(array_unique($values)) !== 6) {
            $this->addFlash('error', 'Tu ne peux utiliser chaque chiffre (1 à 6) qu\'une seule fois.');
            return $this->redirectToRoute('app_questionnaire');
        }

        $zoneRatings = [];
        foreach ($letters as $letter) {
            $zoneRatings[self::AFFIRMATIONS[$letter]['zone']] = (int) $ratings[$letter];
        }

        // saveRatings persiste sans flush et retourne les ratings triés
        // generateForUser les utilise directement
        $sortedRatings = $this->userService->saveRatings($student, $zoneRatings);
        $this->parcoursService->generateForUser($student, $sortedRatings);

        return $this->redirectToRoute('app_map');
    }
}
