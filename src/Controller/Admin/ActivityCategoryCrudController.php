<?php

namespace App\Controller\Admin;

use App\Entity\ActivityCategory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<ActivityCategory> */
class ActivityCategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ActivityCategory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Catégorie')
            ->setEntityLabelInPlural('Catégories')
            ->setSearchFields(['type']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('type', 'Type de catégorie');
        yield IntegerField::new('nbrPoints', 'Points attribués');
        yield DateTimeField::new('beginningHourCategory', 'Heure de début')->setFormat('HH:mm')->hideOnIndex();
    }
}
