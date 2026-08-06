<?php

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Service\EventResetService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class EventCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EventResetService $resetService,
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $urlGenerator,
    ) {}

    public static function getEntityFqcn(): string
    {
        return Event::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Event')
            ->setEntityLabelInPlural('Events')
            ->setDefaultSort(['beginningHourEvent' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield DateTimeField::new('beginningHourEvent', 'Début');
        yield DateTimeField::new('endHourEvent', 'Fin');
        yield DateTimeField::new('resetAt', 'Réinitialisé le')->onlyOnIndex()->setDisabled(true);
    }

    public function configureActions(Actions $actions): Actions
    {
        $reset = Action::new('resetEvent', 'Réinitialiser', 'fa fa-refresh')
            ->linkToCrudAction('resetEvent')
            ->setCssClass('btn btn-warning')
            ->displayIf(static fn(Event $e) => !$e->isReset());

        return $actions
            ->add(Crud::PAGE_INDEX, $reset)
            ->add(Crud::PAGE_DETAIL, $reset);
    }

    #[AdminRoute(path: '/reset-event', name: 'reset_event')]
    public function resetEvent(AdminContext $context): Response
    {
        /** @var Event $event */
        $event = $context->getEntity()->getInstance();

        $this->resetService->reset($event);
        $this->em->flush();

        $this->addFlash('success', "Event « {$event->getName()} » réinitialisé avec succès.");

        return $this->redirect(
            $this->urlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl()
        );
    }
}
