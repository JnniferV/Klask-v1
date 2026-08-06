<?php

namespace App\Controller\Admin;

use App\Entity\Activity;
use App\Service\ActivityService;
use App\Service\MapService;
use App\Service\RealtimeNotifier;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class ActivityCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ActivityService $activityService,
        private readonly MapService $mapService,
        private readonly RealtimeNotifier $notifier,
    ) {}

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
        yield IntegerField::new('estimatedWaitMinutes', 'Attente (min)')->hideOnIndex();
        // Position posée via « Placer sur la carte » — consultation seule
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

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $place)
            ->add(Crud::PAGE_EDIT, $place);
    }

    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->guardStandSphere($entityInstance);
        $this->activityService->initQrCode($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
        $this->mapService->invalidateCache();
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->guardStandSphere($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
        $this->mapService->invalidateCache();

        // Notifie tous les clients en temps réel : position, dispo, attente...
        if ($entityInstance instanceof Activity) {
            $this->notifier->publish('map-update', [
                ...$this->mapService->activityToArray($entityInstance),
                'action' => 'update',
                'type'   => 'activity',
            ]);
        }
    }

    public function deleteEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        parent::deleteEntity($entityManager, $entityInstance);
        $this->mapService->invalidateCache();
    }

    private function guardStandSphere(mixed $activity): void
    {
        if ($activity instanceof Activity && $activity->isStand() && $activity->getSphere() === null) {
            throw new \LogicException('Un Stand doit obligatoirement être rattaché à une sphère.');
        }
    }
}
