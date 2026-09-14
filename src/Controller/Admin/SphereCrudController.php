<?php

namespace App\Controller\Admin;

use App\Entity\Sphere;
use App\Service\MapService;
use App\Service\RealtimeNotifier;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<Sphere> */
class SphereCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MapService $mapService,
        private readonly RealtimeNotifier $notifier,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Sphere::class;
    }

    public function persistEntity(EntityManagerInterface $em, mixed $entity): void
    {
        parent::persistEntity($em, $entity);
        $this->mapService->invalidateCache();
    }

    public function updateEntity(EntityManagerInterface $em, mixed $entity): void
    {
        parent::updateEntity($em, $entity);
        $this->mapService->invalidateCache();

        if ($entity instanceof Sphere) {
            $this->notifier->publish('map-update', [
                'type' => 'sphere',
                'id' => $entity->getId(),
                'name' => $entity->getName(),
                'color' => $entity->getColor(),
            ]);
        }
    }

    public function deleteEntity(EntityManagerInterface $em, mixed $entity): void
    {
        parent::deleteEntity($em, $entity);
        $this->mapService->invalidateCache();
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Sphère')
            ->setEntityLabelInPlural('Sphères')
            ->setSearchFields(['name']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield ColorField::new('color', 'Couleur');
        yield AssociationField::new('activities', 'Activités')->hideOnIndex();
        // géométrie calculée depuis les stands ou posée via « placer sur la carte », consultation seule
        yield NumberField::new('pointX', 'Centre X (%)')->setNumDecimals(2)->onlyOnDetail();
        yield NumberField::new('pointY', 'Centre Y (%)')->setNumDecimals(2)->onlyOnDetail();
        yield NumberField::new('radius', 'Rayon (%)')->setNumDecimals(2)->onlyOnDetail();
    }

    public function configureActions(Actions $actions): Actions
    {
        $place = Action::new('placeOnMap', 'Placer sur la carte', 'fa fa-map-marker-alt')
            ->linkToRoute('admin_map_placement', fn (Sphere $s) => ['type' => 'sphere', 'id' => $s->getId()])
            ->setCssClass('btn btn-success');

        return $actions->add(Crud::PAGE_INDEX, $place)->add(Crud::PAGE_EDIT, $place);
    }
}
