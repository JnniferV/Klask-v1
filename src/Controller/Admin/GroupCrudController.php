<?php

namespace App\Controller\Admin;

use App\Entity\Group;
use App\Service\GroupCodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

/** @extends AbstractCrudController<Group> */
class GroupCrudController extends AbstractCrudController
{
    public function __construct(private readonly GroupCodeGenerator $codeGenerator)
    {
    }

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

    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        if ('' === $entityInstance->getCode()) {
            $entityInstance->setCode($this->codeGenerator->generate());
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('code', 'Code groupe')
            ->setFormTypeOption('disabled', true)
            ->setHelp('Généré automatiquement à la création.');
        yield ChoiceField::new('name', 'Niveau')->setChoices(array_combine(Group::LEVELS, Group::LEVELS));
        yield ColorField::new('color', 'Couleur');
        // formulaire imbriqué dans un autre CRUD (établissement)
        if (self::class === $this->getContext()?->getCrud()?->getControllerFqcn()) {
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
