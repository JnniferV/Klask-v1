<?php

namespace App\Controller\Admin;

use App\Repository\AppParameterRepository;
use App\Repository\GroupRepository;
use App\Repository\ScanRepository;
use App\Repository\UserRepository;
use App\Service\AppParameterService;
use App\Service\RealtimeNotifier;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Component\Routing\Attribute\Route;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    private const PARAM_GROUPS = [
        'Scans'             => ['INVALID_SCAN_THRESHOLD', 'BLOCK_DURATION_MINUTES', 'SCAN_DELAY_MINUTES'],
        'Inscription'       => ['MAX_STUDENTS_PER_GROUP'],
        'Événements'        => ['ALERT_BEFORE_EVENT_MIN'],
        'Points'            => ['BONUS_TOP3_SPHERES', 'BONUS_ALL_SPHERES', 'BONUS_MAX_SCORE'],
        'Capacité stands'   => ['SOFT_CAPACITY_MARGIN'],
        'Carte'             => ['ALERT_MAP_ACTIVE'],
    ];

    public function __construct(
        private readonly AdminUrlGenerator      $urlGenerator,
        private readonly UserRepository         $userRepository,
        private readonly GroupRepository        $groupRepository,
        private readonly ScanRepository         $scanRepository,
        private readonly AppParameterService    $params,
        private readonly AppParameterRepository $paramRepo,
        private readonly EntityManagerInterface $em,
        private readonly RealtimeNotifier       $notifier,
    ) {}

    public function index(): Response
    {
        $g = $this->urlGenerator;

        return $this->render('admin/dashboard.html.twig', [
            'alertMapActive' => $this->params->getBool('ALERT_MAP_ACTIVE'),
            'paramGroups'    => $this->buildParamGroups(),
            'links' => [
                'activities'     => $g->setController(ActivityCrudController::class)->setAction(Action::INDEX)->generateUrl(),
                'spheres'        => $g->setController(SphereCrudController::class)->setAction(Action::INDEX)->generateUrl(),
                'categories'     => $g->setController(ActivityCategoryCrudController::class)->setAction(Action::INDEX)->generateUrl(),
                'groups'         => $g->setController(GroupCrudController::class)->setAction(Action::INDEX)->generateUrl(),
                'establishments' => $g->setController(EstablishmentCrudController::class)->setAction(Action::INDEX)->generateUrl(),
                'users'          => $g->setController(UserCrudController::class)->setAction(Action::INDEX)->generateUrl(),
                'accompanying'   => $g->setController(AccompanyingCrudController::class)->setAction(Action::INDEX)->generateUrl(),
                'events'         => $g->setController(EventCrudController::class)->setAction(Action::INDEX)->generateUrl(),
                'notifications'  => $g->setController(NotificationCrudController::class)->setAction(Action::INDEX)->generateUrl(),
            ],
        ]);
    }

    #[AdminRoute(path: '/stats', name: 'stats')]
    public function stats(): Response
    {
        return $this->render('admin/stats.html.twig', [
            'topStudents'    => $this->userRepository->findTopStudents(10),
            'topGroups'      => $this->groupRepository->findTopGroups(5),
            'sphereVisits'   => $this->scanRepository->findMostVisitedSpheres(),
            'totalScans'     => $this->scanRepository->countAll(),
        ]);
    }

    // point unique des paramètres
    #[Route('/admin/parameters/save', name: 'admin_parameters_save', methods: ['POST'])]
    public function saveParameters(Request $request): JsonResponse
    {
        $data = $request->request->all('params');

        foreach ($this->paramRepo->findAll() as $param) {
            $key    = $param->getParamKey();
            $isBool = $param->getParamType() === 'boolean';

            if (\array_key_exists($key, $data)) {
                $param->setParamValue($isBool
                    ? (filter_var($data[$key], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false')
                    : (string) $data[$key]);
            } elseif ($isBool) {
                $param->setParamValue('false'); // case décochée = absente du POST
            } else {
                continue;
            }

            $this->params->invalidate($key);
        }

        $this->em->flush();
        $this->notifier->publish('map-update', ['type' => 'alert-map', 'active' => $this->params->getBool('ALERT_MAP_ACTIVE')]);

        return $this->json(['ok' => true]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Klask — Admin')
            ->setFaviconPath('favicon.ico')
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

        yield MenuItem::section('Event');
        yield MenuItem::linkTo(EventCrudController::class, 'Events', 'fa fa-calendar');
        yield MenuItem::linkTo(GroupCrudController::class, 'Groupes', 'fa fa-users');
        yield MenuItem::linkTo(EstablishmentCrudController::class, 'Établissements', 'fa fa-school');

        yield MenuItem::section('Utilisateurs');
        yield MenuItem::linkTo(UserCrudController::class, 'Élèves', 'fa fa-graduation-cap');
        yield MenuItem::linkTo(AccompanyingCrudController::class, 'Accompagnateurs', 'fa fa-user-tie');

        yield MenuItem::section('Administration');
        yield MenuItem::linkToRoute('Statistiques', 'fa fa-chart-bar', 'admin_stats');
        yield MenuItem::linkTo(NotificationCrudController::class, 'Notifications', 'fa fa-bell');

        yield MenuItem::section('');
        yield MenuItem::linkToUrl('Déconnexion', 'fa fa-sign-out', $this->generateUrl('app_logout'));
    }

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
