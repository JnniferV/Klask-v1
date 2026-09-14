<?php

namespace App\Controller\Admin;

use App\Entity\Activity;
use App\Entity\Sphere;
use App\Repository\ActivityCategoryRepository;
use App\Repository\ActivityRepository;
use App\Repository\SphereRepository;
use App\Service\ActivityService;
use App\Service\MapService;
use App\Service\RealtimeNotifier;
use App\Service\SphereBoundsCalculator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class MapPlacementController extends AbstractController
{
    public function __construct(
        private readonly MapService $mapService,
        private readonly ActivityService $activityService,
        private readonly AdminUrlGenerator $urlGenerator,
        private readonly SphereRepository $sphereRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly ActivityCategoryRepository $activityCategoryRepository,
        private readonly RealtimeNotifier $notifier,
    ) {
    }

    #[Route('/admin/placement/{type}/{id}', name: 'admin_map_placement', requirements: ['type' => 'sphere|activity'], methods: ['GET', 'POST'])]
    public function __invoke(Request $request, string $type, int $id): Response
    {
        $entity = match ($type) {
            'sphere' => $this->sphereRepository->find($id),
            'activity' => $this->activityRepository->find($id),
            default => null,
        };

        if (!$entity instanceof Sphere && !$entity instanceof Activity) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('admin_map_placement', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

                return $this->redirectToRoute('admin_map_placement', compact('type', 'id'));
            }

            $x = (float) $request->request->get('pointX', 50);
            $y = (float) $request->request->get('pointY', 50);
            $radius = $entity instanceof Sphere ? (float) $request->request->get('radius', $entity->getRadius()) : null;

            $this->mapService->savePosition($entity, $x, $y, $radius);
            $this->mapService->invalidateCache();
            $this->notifier->publish('map-update', $this->buildMoveEvent($entity));
            $this->addFlash('success', 'Position enregistrée.');
            $this->warnIfOverlap($entity);

            return $this->redirectToRoute('admin_map_placement', compact('type', 'id'));
        }

        $crudController = 'sphere' === $type ? SphereCrudController::class : ActivityCrudController::class;
        $backUrl = $this->urlGenerator
            ->setController($crudController)
            ->setAction(Action::EDIT)
            ->setEntityId($id)
            ->generateUrl();

        $suggestedBounds = $entity instanceof Sphere
            ? SphereBoundsCalculator::fromStands($this->activityRepository->findStandsBySphere($entity))
            : null;

        return $this->render('admin/placement.html.twig', [
            'type' => $type,
            'id' => $id,
            'label' => $entity->getName(),
            'pointX' => $entity->getPointX() ?? 50,
            'pointY' => $entity->getPointY() ?? 50,
            'radius' => $entity instanceof Sphere ? $entity->getRadius() : null,
            'suggestedBounds' => $suggestedBounds,
            'backUrl' => $backUrl,
            'categories' => 'sphere' === $type ? $this->activityCategoryRepository->findAll() : [],
            'mapJson' => $this->mapService->getPreparedSpheresJson(),
        ]);
    }

    #[Route('/admin/activity/new-on-map', name: 'admin_activity_new_on_map', methods: ['POST'])]
    public function newActivityOnMap(Request $request): Response
    {
        $sphereId = (int) $request->request->get('sphereId');

        if (!$this->isCsrfTokenValid('admin_activity_new_on_map', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('admin_map_placement', ['type' => 'sphere', 'id' => $sphereId]);
        }

        $sphere = $this->sphereRepository->find($sphereId);
        $category = $this->activityCategoryRepository->find((int) $request->request->get('categoryId'));

        if (!$sphere || !$category) {
            throw $this->createNotFoundException();
        }

        $name = trim((string) $request->request->get('name'));

        $refus = match (true) {
            '' === $name => 'Le nom est obligatoire.',
            null !== $this->activityRepository->findOneBy(['name' => $name]) => sprintf('Une activité nommée « %s » existe déjà.', $name),
            default => null,
        };

        if (null !== $refus) {
            $this->addFlash('error', $refus);

            return $this->redirectToRoute('admin_map_placement', ['type' => 'sphere', 'id' => $sphereId]);
        }

        $activity = $this->activityService->createFromMap(
            $sphere,
            $category,
            $name,
            $request->request->getString('description') ?: null,
            (float) $request->request->get('pointX', 50),
            (float) $request->request->get('pointY', 50),
        );

        // invalider avant de publier
        $this->mapService->invalidateCache();
        $this->notifier->publish('map-update', [
            ...$this->mapService->activityToArray($activity),
            'action' => 'create',
            'type' => 'activity',
        ]);

        $this->addFlash('success', sprintf('Activité "%s" créée.', $activity->getName()));

        return $this->redirectToRoute('admin_map_placement', ['type' => 'sphere', 'id' => $sphereId]);
    }

    private function warnIfOverlap(Sphere|Activity $entity): void
    {
        $movedSphere = $entity instanceof Sphere ? $entity : $entity->getSphere();
        if (null === $movedSphere) {
            return;
        }

        $spheres = $this->sphereRepository->findAll();
        $allIds = array_map(fn (Sphere $s) => (int) $s->getId(), $spheres);
        $grouped = $this->activityRepository->findStandsBySpheres($allIds);

        $movedBounds = SphereBoundsCalculator::fromStands($grouped[$movedSphere->getId()] ?? []);
        if (null === $movedBounds) {
            return;
        }

        foreach ($spheres as $other) {
            if ($other->getId() === $movedSphere->getId()) {
                continue;
            }
            $otherBounds = SphereBoundsCalculator::fromStands($grouped[$other->getId()] ?? []);
            if (null !== $otherBounds && SphereBoundsCalculator::overlap($movedBounds, $otherBounds)) {
                $this->addFlash('warning', sprintf(
                    '⚠ La sphère "%s" chevauche "%s" — pensez à espacer les stands.',
                    $movedSphere->getName(),
                    $other->getName(),
                ));
            }
        }
    }

    // position déjà persistée : on relit l'entité, pas besoin de repasser les coords
    /** @return array<string, mixed> */
    private function buildMoveEvent(Sphere|Activity $entity): array
    {
        if ($entity instanceof Sphere) {
            return [
                'action' => 'move',
                'type' => 'sphere',
                'id' => $entity->getId(),
                'centerX' => $entity->getPointX(),
                'centerY' => $entity->getPointY(),
                'radius' => $entity->getRadius(),
            ];
        }

        return [
            ...$this->mapService->activityToArray($entity),
            'action' => 'move',
            'type' => 'activity',
        ];
    }
}
