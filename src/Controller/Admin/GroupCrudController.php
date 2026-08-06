<?php

namespace App\Controller\Admin;

use App\Entity\Group;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class GroupCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Group::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Groupe')
            ->setEntityLabelInPlural('Groupes')
            ->setDefaultSort(['code' => 'ASC'])
            ->setSearchFields(['code', 'name']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('code', 'Code groupe');
        yield TextField::new('name', 'Niveau (Seconde…)');
        yield ColorField::new('color', 'Couleur');
        // Formulaire imbriqué dans un autre CRUD (établissement)
        if ($this->getContext()?->getCrud()?->getControllerFqcn() === self::class) {
            yield AssociationField::new('establishment', 'Établissement');
        }
        yield AssociationField::new('event', 'Événement');
        yield AssociationField::new('users', 'Élèves')->onlyOnIndex();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('establishment', 'Établissement'))
            ->add(EntityFilter::new('event', 'Événement'));
    }
}
