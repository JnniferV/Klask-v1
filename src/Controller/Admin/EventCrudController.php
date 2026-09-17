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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** @extends AbstractCrudController<Event> */
class EventCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EventResetService $resetService,
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $urlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Event::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Event')
            ->setEntityLabelInPlural('Events')
            // en dropdown easyadmin ignore le template de l'action et la rend en lien GET
            ->showEntityActionsInlined()
            ->setDefaultSort(['beginningHourEvent' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Nom');
        yield DateTimeField::new('beginningHourEvent', 'Début');
        yield DateTimeField::new('endHourEvent', 'Fin');
        yield DateTimeField::new('resetAt', 'Dernière réinit.')->onlyOnIndex()->setDisabled(true);
    }

    public function configureActions(Actions $actions): Actions
    {
        $reset = Action::new('resetEvent', 'Réinitialiser', 'fa fa-refresh')
            ->linkToCrudAction('resetEvent')
            ->setCssClass('btn btn-warning')
            // pas de displayIf : reset rejouable, resetAt devient une date
            ->setTemplatePath('admin/actions/reset_event.html.twig');

        return $actions
            ->add(Crud::PAGE_INDEX, $reset)
            ->add(Crud::PAGE_DETAIL, $reset);
    }

    /** @param AdminContext<Event> $context */
    #[AdminRoute(path: '/reset-event', name: 'reset_event', options: ['methods' => ['POST']])]
    public function resetEvent(AdminContext $context, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('reset_event', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

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
