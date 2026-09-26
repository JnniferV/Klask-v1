<?php

namespace App\Controller\Admin;

use App\Entity\Activity;
use App\Entity\ActivityCategory;
use App\Service\ActivityService;
use App\Service\MapService;
use App\Service\RealtimeNotifier;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** @extends AbstractCrudController<Activity> */
class ActivityCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ActivityService $activityService,
        private readonly MapService $mapService,
        private readonly RealtimeNotifier $notifier,
        private readonly AdminUrlGenerator $urlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Activity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Activité')
            ->setEntityLabelInPlural('Activités / Stands')
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name', 'description']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield AssociationField::new('sphere', 'Sphère');
        yield AssociationField::new('category', 'Catégorie');
        yield BooleanField::new('isAvailable', 'Disponible');
        // affichage seul, le bouton Halo fait la bascule
        yield BooleanField::new('isHighlighted', 'Halo')->renderAsSwitch(false)->hideOnForm();
        yield IntegerField::new('estimatedWaitMinutes', 'Attente (min)')->hideOnIndex();
        // position posée via « placer sur la carte », consultation seule
        yield NumberField::new('pointX', 'Position X (%)')->setNumDecimals(2)->onlyOnDetail();
        yield NumberField::new('pointY', 'Position Y (%)')->setNumDecimals(2)->onlyOnDetail();
        yield IntegerField::new('softLimit', 'Limite souple')->hideOnIndex();
        yield IntegerField::new('hardLimit', 'Limite dure')->hideOnIndex();
        yield BooleanField::new('isInternship', 'Stage')->hideOnIndex();
        yield TextField::new('qrcodeToken', 'Token QR')->hideOnForm()->setFormTypeOption('disabled', true);
        yield TextField::new('qrcode', 'Image QR')->hideOnIndex()->hideOnForm();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isAvailable', 'Disponible'))
            ->add(EntityFilter::new('sphere', 'Sphère'))
            ->add(EntityFilter::new('category', 'Catégorie'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $place = Action::new('placeOnMap', 'Placer sur la carte', 'fa fa-map-marker-alt')
            ->linkToRoute('admin_map_placement', fn (Activity $a) => ['type' => 'activity', 'id' => $a->getId()])
            ->setCssClass('btn btn-success');

        // que pour les ateliers et conférences
        $halo = Action::new('toggleHighlight', 'Halo', 'fa fa-lightbulb')
            ->linkToCrudAction('toggleHighlight')
            ->setCssClass('btn btn-warning')
            ->setTemplatePath('admin/actions/toggle_highlight.html.twig')
            ->displayIf(fn (Activity $a): bool => in_array($a->getCategory()?->getType(), ActivityCategory::SCHEDULED_TYPES, true));

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $place)
            ->add(Crud::PAGE_INDEX, $halo)
            ->add(Crud::PAGE_EDIT, $place);
    }

    // updateEntity enregistre et prévient les cartes ouvertes
    /** @param AdminContext<Activity> $context */
    #[AdminRoute(path: '/toggle-highlight', name: 'toggle_highlight', options: ['methods' => ['POST']])]
    public function toggleHighlight(AdminContext $context, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('toggle_highlight', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        /** @var Activity $activity */
        $activity = $context->getEntity()->getInstance();
        $activity->setIsHighlighted(!$activity->isHighlighted());

        $this->updateEntity($em, $activity);
        $this->addFlash('success', sprintf('Halo %s pour « %s ».', $activity->isHighlighted() ? 'allumé' : 'éteint', (string) $activity->getName()));

        return $this->redirect(
            $this->urlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl()
        );
    }

    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->activityService->initQrCode($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
        $this->mapService->invalidateCache();
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        parent::updateEntity($entityManager, $entityInstance);
        $this->mapService->invalidateCache();

        // notifie tous les clients en temps réel : position, dispo, attente
        if ($entityInstance instanceof Activity) {
            $this->notifier->publish('map-update', [
                ...$this->mapService->activityToArray($entityInstance),
                'action' => 'update',
                'type' => 'activity',
            ]);
        }
    }

    public function deleteEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $id = null;
        if ($entityInstance instanceof Activity) {
            // id et fichier QR perdus après, on les traite avant
            $id = $entityInstance->getId();
            $this->activityService->deleteQrCode($entityInstance);
        }

        parent::deleteEntity($entityManager, $entityInstance);
        $this->mapService->invalidateCache();

        // même canal que l'update, le pin disparaît sans recharger
        if (null !== $id) {
            $this->notifier->publish('map-update', ['type' => 'activity', 'action' => 'delete', 'id' => $id]);
        }
    }
}
