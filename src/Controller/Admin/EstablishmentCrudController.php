<?php

namespace App\Controller\Admin;

use App\Entity\Establishment;
use App\Service\GroupCodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<Establishment> */
class EstablishmentCrudController extends AbstractCrudController
{
    public function __construct(private readonly GroupCodeGenerator $codeGenerator)
    {
    }

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

    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->generateMissingGroupCodes($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->generateMissingGroupCodes($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function generateMissingGroupCodes(Establishment $establishment): void
    {
        foreach ($establishment->getGroups() as $group) {
            if ('' === $group->getCode()) {
                $group->setCode($this->codeGenerator->generate());
            }
        }
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
