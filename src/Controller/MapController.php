<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\MercureTopics;
use App\Service\AppParameterService;
use App\Service\MapService;
use App\Service\NotificationReplay;
use App\Service\ParcoursService;
use App\Service\ScanService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Routing\Attribute\Route;

class MapController extends AbstractController
{
    private const SESSION_INTRO_ACCOMPANYING = 'accompanying_instructions_seen';
    private const SESSION_INTRO_STUDENT = 'student_guide_seen';
    private const IMG_ACCOMPANYING = [
        'light' => 'images/instructionsaccompagnateurclair.webp',
        'dark' => 'images/instructionsaccompagnateursombre.webp',
    ];
    private const IMG_STUDENT = [
        'light' => 'images/instructionsvisiteurclair.webp',
        'dark' => 'images/instructionsvisiteursombre.webp',
    ];

    public function __construct(
        private readonly MapService $mapService,
        private readonly UserService $userService,
        private readonly UserRepository $userRepository,
        private readonly ParcoursService $parcoursService,
        private readonly RequestStack $requestStack,
        private readonly AppParameterService $params,
        private readonly Authorization $authorization,
        private readonly MercureTopics $mercureTopics,
        private readonly NotificationReplay $notificationReplay,
    ) {
    }

    #[Route('/map', name: 'app_map', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        $userScore = 0;
        $groupScore = 0;
        $showIntro = false;
        $introImages = null;
        $topSphereIds = [];
        $bottomSphereIds = [];
        $parcoursIds = [];
        $groupStudents = [];
        $scannedIds = [];

        if ($user instanceof User) {
            $session = $this->requestStack->getSession();

            if ($this->isGranted('ROLE_ACCOMPANYING')) {
                $introImages = self::IMG_ACCOMPANYING;
                $showIntro = !$session->get(self::SESSION_INTRO_ACCOMPANYING.'_'.$user->getId());
                $group = $user->getGroup();
                $groupStudents = null !== $group ? $this->userRepository->findStudentScoresByGroup($group) : [];
            } elseif ($this->isGranted('ROLE_STUDENT')) {
                ['top' => $topSphereIds, 'bottom' => $bottomSphereIds]
                    = $this->userService->getTopAndBottomSphereIds($user);
                if (empty($topSphereIds)) {
                    return $this->redirectToRoute('app_questionnaire');
                }
                $introImages = self::IMG_STUDENT;
                $showIntro = !$session->get(self::SESSION_INTRO_STUDENT.'_'.$user->getId());
                $pathData = $this->parcoursService->getPathForMap($user);
                $parcoursIds = $pathData['steps'];
                $scannedIds = $pathData['scannedIds'];
            }

            $userScore = $user->getScore() ?? 0;
            $groupScore = $user->getGroup()?->getScore() ?? 0;
        }

        $topics = $this->mercureTopics->forUser($user instanceof User ? $user : null);

        $response = $this->render('map/map.html.twig', [
            'currentUser' => $user,
            'mercureTopics' => $topics,
            'userScore' => $userScore,
            'groupScore' => $groupScore,
            'groupStudents' => $groupStudents,
            'spheresJson' => $this->mapService->getPreparedSpheresJson(),
            'topSpheresJson' => json_encode($topSphereIds),
            'bottomSpheresJson' => json_encode($bottomSphereIds),
            'parcoursJson' => json_encode($parcoursIds),
            'scannedJson' => json_encode($scannedIds),
            'showIntro' => $showIntro,
            'instructionImages' => $introImages,
            'alertMapActive' => $this->params->getBool('ALERT_MAP_ACTIVE'),
            'inactivityMin' => $this->params->getInt('INACTIVITY_MINUTES', 25),
            'pointsOutsideTop3' => ScanService::POINTS_OUTSIDE_TOP3,
        ]);


        $response->headers->set('Cache-Control', 'no-store');

        // les updates étant privées, l'abonné doit présenter un JWT limité à ses topics
        // /map est la seule page qui ouvre un EventSource, le cookie se pose ici
        $response->headers->setCookie($this->authorization->createCookie($request, $topics));

        return $response;
    }

    #[Route('/map/notifications', name: 'app_map_notifications', methods: ['GET'])]
    public function notifications(): JsonResponse
    {
        $user = $this->getUser();
        $response = $this->json($this->notificationReplay->forUser($user instanceof User ? $user : null));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/map/intro/ack', name: 'app_map_intro_ack', methods: ['POST'])]
    public function ackIntro(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false]);
        }

        $session = $this->requestStack->getSession();

        if ($this->isGranted('ROLE_ACCOMPANYING')) {
            $session->set(self::SESSION_INTRO_ACCOMPANYING.'_'.$user->getId(), true);
        } elseif ($this->isGranted('ROLE_STUDENT')) {
            $session->set(self::SESSION_INTRO_STUDENT.'_'.$user->getId(), true);
        }

        return $this->json(['ok' => true]);
    }
}
