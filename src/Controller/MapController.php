<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Service\AppParameterService;
use App\Service\MapService;
use App\Service\ParcoursService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MapController extends AbstractController
{
    private const SESSION_INTRO_ACCOMPANYING = 'accompanying_instructions_seen';
    private const SESSION_INTRO_STUDENT      = 'student_guide_seen';
    private const IMG_ACCOMPANYING           = 'images/instructions_accompanying.webp';
    private const IMG_STUDENT                = 'images/instructions_student.webp';
    //une notification publiée dans ce délai est rejouée à l'ouverture
    private const NOTIFICATION_REPLAY_MINUTES = 10;

    public function __construct(
        private readonly MapService             $mapService,
        private readonly UserService            $userService,
        private readonly UserRepository         $userRepository,
        private readonly ParcoursService        $parcoursService,
        private readonly RequestStack           $requestStack,
        private readonly AppParameterService $params,
        private readonly NotificationRepository $notificationRepository,
    ) {}

    #[Route('/map', name: 'app_map', methods: ['GET'])]
    public function index(): Response
    {
        $user            = $this->getUser();
        $userScore       = 0;
        $groupScore      = 0;
        $showIntro       = false;
        $introImage      = null;
        $topSphereIds    = [];
        $bottomSphereIds = [];
        $parcoursIds     = [];
        $groupStudents   = [];
        $scannedIds      = [];

        if ($user instanceof User) {
            $session = $this->requestStack->getSession();

            if ($this->isGranted('ROLE_ACCOMPANYING')) {
                $introImage    = self::IMG_ACCOMPANYING;
                $showIntro     = !$session->get(self::SESSION_INTRO_ACCOMPANYING);
                $group         = $user->getGroup();
                $groupStudents = $group !== null ? $this->userRepository->findStudentScoresByGroup($group) : [];
            } elseif ($this->isGranted('ROLE_STUDENT')) {
                ['top' => $topSphereIds, 'bottom' => $bottomSphereIds]
                    = $this->userService->getTopAndBottomSphereIds($user);
                if (empty($topSphereIds)) {
                    return $this->redirectToRoute('app_questionnaire');
                }
                $introImage   = self::IMG_STUDENT;
                $showIntro    = !$session->get(self::SESSION_INTRO_STUDENT);
                $pathData     = $this->parcoursService->getPathForMap($user);
                $parcoursIds  = $pathData['steps'];
                $scannedIds   = $pathData['scannedIds'];
            }

            $userScore  = $user->getScore() ?? 0;
            $groupScore = $user->getGroup()?->getScore() ?? 0;
        }

        $topics = $this->mercureTopics($user);

        return $this->render('map/map.html.twig', [
            'currentUser'       => $user,
            'mercureTopics'     => $topics,
            'lastNotification'  => $this->notificationToReplay($topics),
            'userScore'         => $userScore,
            'groupScore'        => $groupScore,
            'groupStudents'     => $groupStudents,
            'spheresJson'       => $this->mapService->getPreparedSpheresJson(),
            'topSpheresJson'    => json_encode($topSphereIds),
            'bottomSpheresJson' => json_encode($bottomSphereIds),
            'parcoursJson'      => json_encode($parcoursIds),
            'scannedJson'       => json_encode($scannedIds),
            'showIntro'         => $showIntro,
            'instructionImage'  => $introImage,
            'alertMapActive'    => $this->params->getBool('ALERT_MAP_ACTIVE'),
        ]);
    }

    /**
     * Topics Mercure qui concernent cet utilisateur
     * @return string[]
     */
    private function mercureTopics(?User $user): array
    {
        $topics = ['map-update', 'event-alert'];

        if (!$user instanceof User) {
            return $topics;
        }

        $code = $user->getGroup()?->getCode();

        if ($this->isGranted('ROLE_STUDENT')) {
            $topics[] = 'poke/' . $user->getId();
            $topics[] = 'event-alert/student';
            $topics[] = 'event-alert/user/' . $user->getPseudo();
            if ($code !== null) {
                $topics[] = 'event-alert/class/' . $code;
            }
        } elseif ($this->isGranted('ROLE_ACCOMPANYING')) {
            $topics[] = 'event-alert/accompagnateur';
            $topics[] = 'event-alert/user/' . $user->getEmail();
            if ($code !== null) {
                $topics[] = 'group-score/' . $code;
            }
        }

        return $topics;
    }

    /**
     * dernière notif publiée qui cible cet utilisateur
     * @param string[] $topics
     */
    private function notificationToReplay(array $topics): ?array
    {
        $since = new \DateTimeImmutable('-' . self::NOTIFICATION_REPLAY_MINUTES . ' minutes');

        foreach ($this->notificationRepository->findSentSince($since) as $notification) {
            if (in_array($notification->getMercureTopic(), $topics, true)) {
                return $notification->toMercurePayload();
            }
        }

        return null;
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
            $session->set(self::SESSION_INTRO_ACCOMPANYING, true);
        } elseif ($this->isGranted('ROLE_STUDENT')) {
            $session->set(self::SESSION_INTRO_STUDENT, true);
        }

        return $this->json(['ok' => true]);
    }
}
