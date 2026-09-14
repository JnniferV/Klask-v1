<?php

namespace App\Controller\Admin;

use App\Entity\Scan;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

/** @extends AbstractCrudController<Scan> */
class ScanCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Scan::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Scan')
            ->setEntityLabelInPlural('Historique des scans')
            ->setDefaultSort(['hourValidation' => 'DESC'])
            ->setPaginatorPageSize(50);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        // 50 lignes par page x 2 associations affichées : sans ces addSelect, ~100 requêtes par page
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->leftJoin('entity.user', 'u')->addSelect('u')
            ->leftJoin('entity.activity', 'act')->addSelect('act');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID');
        yield DateTimeField::new('hourValidation', 'Horodatage')->setFormat('dd/MM/yyyy HH:mm:ss');
        yield AssociationField::new('user', 'Élève');
        yield AssociationField::new('activity', 'Stand / Activité');
    }
}
