<?php

namespace App\Controller\Admin;

use App\Entity\ActivityCategory;
use App\Repository\ActivityRepository;
use App\Repository\AppParameterRepository;
use App\Repository\GroupRepository;
use App\Repository\ScanRepository;
use App\Repository\UserRepository;
use App\Service\AppParameterService;
use App\Service\BilanService;
use App\Service\RealtimeNotifier;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    private const PARAM_GROUPS = [
        'Scans' => ['INVALID_SCAN_THRESHOLD', 'BLOCK_DURATION_MINUTES', 'SCAN_DELAY_MINUTES', 'INACTIVITY_MINUTES'],
        'Inscription' => ['MAX_STUDENTS_PER_GROUP'],
        'Événements' => ['ALERT_BEFORE_EVENT_MIN'],
        'Points' => ['BONUS_TOP3_SPHERES', 'BONUS_ALL_SPHERES', 'BONUS_MAX_SCORE'],
        'Capacité stands' => ['SOFT_CAPACITY_MARGIN'],
        'Sphères' => ['MAX_STUDENTS_PER_SPHERE'],
        'Carte' => ['ALERT_MAP_ACTIVE'],
    ];

    public function __construct(
        private readonly AdminUrlGenerator $urlGenerator,
        private readonly ActivityRepository $activityRepository,
        private readonly UserRepository $userRepository,
        private readonly GroupRepository $groupRepository,
        private readonly ScanRepository $scanRepository,
        private readonly AppParameterService $params,
        private readonly AppParameterRepository $paramRepo,
        private readonly EntityManagerInterface $em,
        private readonly RealtimeNotifier $notifier,
        private readonly BilanService $bilanService,
    ) {
    }

    public function index(): Response
    {
        $g = $this->urlGenerator;

        return $this->render('admin/dashboard.html.twig', [
            'alertMapActive' => $this->params->getBool('ALERT_MAP_ACTIVE'),
            'paramGroups' => $this->buildParamGroups(),
            'links' => [
                'activities' => $g->setController(ActivityCrudController::class)->setAction(Action::INDEX)->generateUrl(),
                'spheres' => $g->setController(SphereCrudController::class)->setAction(Action::INDEX)->generateUrl(),
                'categories' => $g->setController(ActivityCategoryCrudController::class)->setAction(Action::INDEX)->generateUrl(),
                'groups' => $g->setController(GroupCrudController::class)->setAction(Action::INDEX)->generateUrl(),
                'establishments' => $g->setController(EstablishmentCrudController::class)->setAction(Action::INDEX)->generateUrl(),
                'users' => $g->setController(UserCrudController::class)->setAction(Action::INDEX)->generateUrl(),
                'accompanying' => $g->setController(AccompanyingCrudController::class)->setAction(Action::INDEX)->generateUrl(),
                'events' => $g->setController(EventCrudController::class)->setAction(Action::INDEX)->generateUrl(),
                'notifications' => $g->setController(NotificationCrudController::class)->setAction(Action::INDEX)->generateUrl(),
            ],
        ]);
    }

    // planches à imprimer : une activité par page, Ctrl+P suffit
    #[AdminRoute(path: '/qrcodes', name: 'qrcodes')]
    public function qrcodes(): Response
    {
        return $this->render('admin/qrcodes.html.twig', [
            'activities' => $this->activityRepository->findBy([], ['name' => 'ASC']),
            'scheduledTypes' => ActivityCategory::SCHEDULED_TYPES,
        ]);
    }

    #[AdminRoute(path: '/stats', name: 'stats')]
    public function stats(): Response
    {
        return $this->render('admin/stats.html.twig', $this->statsData());
    }

    #[AdminRoute(path: '/stats/pdf', name: 'stats_pdf')]
    public function statsPdf(): Response
    {
        $html = $this->renderView('admin/stats_pdf.html.twig', $this->statsData());

        return $this->bilanService->createPdfResponse($html, 'Statistiques_Klask.pdf');
    }

    /** @return array{topStudents: list<array<string, mixed>>, topGroups: list<array<string, mixed>>, sphereVisits: list<array<string, mixed>>, topActivities: list<array<string, mixed>>, studentsByLevel: list<array<string, mixed>>, totalScans: int, internshipScans: int} */
    private function statsData(): array
    {
        return [
            'topStudents' => $this->userRepository->findTopStudents(10),
            'topGroups' => $this->groupRepository->findTopGroups(5),
            'sphereVisits' => $this->scanRepository->findMostVisitedSpheres(),
            'topActivities' => $this->scanRepository->findTopActivities(10),
            'studentsByLevel' => $this->userRepository->findStudentsByLevelAndEstablishment(),
            'totalScans' => $this->scanRepository->countAll(),
            'internshipScans' => $this->scanRepository->countInternshipScans(),
        ];
    }

    // point unique des paramètres
    #[Route('/admin/parameters/save', name: 'admin_parameters_save', methods: ['POST'])]
    public function saveParameters(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin_parameters_save', (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false], 403);
        }

        $data = $request->request->all('params');

        $editable = array_merge(...array_values(self::PARAM_GROUPS));

        foreach ($this->paramRepo->findAll() as $param) {
            $key = $param->getParamKey();
            if (!\in_array($key, $editable, true)) {
                continue;
            }
            $isBool = 'boolean' === $param->getParamType();

            if (\array_key_exists($key, $data)) {
                $param->setParamValue($isBool
                    ? (filter_var($data[$key], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false')
                    : (string) $data[$key]);
            } elseif ($isBool) {
                $param->setParamValue('false'); // case décochée = absente du POST
            }
        }

        $this->em->flush();

        foreach ($editable as $key) {
            $this->params->invalidate($key);
        }
        $this->notifier->publish('map-update', ['type' => 'alert-map', 'active' => $this->params->getBool('ALERT_MAP_ACTIVE')]);

        return $this->json(['ok' => true]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Klask — Admin')
            ->setFaviconPath('images/faviconklask.webp')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToUrl('Voir la carte', 'fa fa-map', $this->generateUrl('app_map'));

        yield MenuItem::section('Carte');
        yield MenuItem::linkTo(SphereCrudController::class, 'Sphères', 'fa fa-circle');
        yield MenuItem::linkTo(ActivityCrudController::class, 'Activités / Stands', 'fa fa-star');
        yield MenuItem::linkTo(ActivityCategoryCrudController::class, 'Catégories', 'fa fa-tag');
        yield MenuItem::linkToRoute('QR codes à imprimer', 'fa fa-print', 'admin_qrcodes');

        yield MenuItem::section('Event');
        yield MenuItem::linkTo(EventCrudController::class, 'Events', 'fa fa-calendar');
        yield MenuItem::linkTo(GroupCrudController::class, 'Groupes', 'fa fa-users');
        yield MenuItem::linkTo(EstablishmentCrudController::class, 'Établissements', 'fa fa-school');

        yield MenuItem::section('Utilisateurs');
        yield MenuItem::linkTo(UserCrudController::class, 'Élèves', 'fa fa-graduation-cap');
        yield MenuItem::linkTo(AccompanyingCrudController::class, 'Accompagnateurs', 'fa fa-user-tie');

        yield MenuItem::section('Administration');
        yield MenuItem::linkToRoute('Statistiques', 'fa fa-chart-bar', 'admin_stats');
        yield MenuItem::linkTo(ScanCrudController::class, 'Historique des scans', 'fa fa-qrcode');
        yield MenuItem::linkTo(NotificationCrudController::class, 'Notifications', 'fa fa-bell');

        yield MenuItem::section('');
        yield MenuItem::linkToUrl('Déconnexion', 'fa fa-sign-out', $this->generateUrl('app_logout'));
    }

    /** @return array<string, \App\Entity\AppParameter[]> */
    private function buildParamGroups(): array
    {
        $byKey = [];
        foreach ($this->params->findAllOrdered() as $p) {
            $byKey[$p->getParamKey()] = $p;
        }

        $groups = [];
        foreach (self::PARAM_GROUPS as $label => $keys) {
            $items = [];
            foreach ($keys as $key) {
                if (isset($byKey[$key])) {
                    $items[] = $byKey[$key];
                }
            }
            if ($items) {
                $groups[$label] = $items;
            }
        }

        return $groups;
    }
}
