<?php

namespace App\Controller\Admin;

use App\Entity\Establishment;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class EstablishmentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Establishment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Établissement')
            ->setEntityLabelInPlural('Établissements')
            ->setSearchFields(['name']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom de l\'établissement');
        yield AssociationField::new('groups', 'Classes')->hideOnForm();
        yield CollectionField::new('groups', 'Classes')
            ->useEntryCrudForm(GroupCrudController::class)
            ->allowAdd()
            ->allowDelete()
            ->onlyOnForms();
    }
}
