<?php

namespace App\Controller\Admin;

use App\Entity\Notification;
use App\Service\RealtimeNotifier;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ColorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

class NotificationCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly RealtimeNotifier $notifier,
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $urlGenerator,
    ) {}

    public static function getEntityFqcn(): string
    {
        return Notification::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Notification')
            ->setEntityLabelInPlural('Notifications')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['title', 'message']);
    }

    // sans heure programmée, la notif part dès sa création
    public function persistEntity(EntityManagerInterface $em, mixed $entity): void
    {
        parent::persistEntity($em, $entity);

        if ($entity instanceof Notification && $entity->getScheduledAt() === null) {
            $this->send($entity);
        }
    }

    // Retirer la date programmée d'une notif jamais envoyée déclenche l'envoi
    public function updateEntity(EntityManagerInterface $em, mixed $entity): void
    {
        parent::updateEntity($em, $entity);

        if ($entity instanceof Notification && $entity->getScheduledAt() === null && !$entity->isSent()) {
            $this->send($entity);
        }
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('title', 'Titre');
        yield TextareaField::new('message', 'Description')->setNumOfRows(2);
        yield UrlField::new('link', 'Lien (optionnel)')->setRequired(false)->hideOnIndex()
            ->setHelp('Affiché comme « En savoir plus » dans la bannière.');
        yield ChoiceField::new('type', 'Type')
            ->setChoices([
                'Information'   => 'info',
                'Succès'        => 'success',
                'Avertissement' => 'warning',
                'Alerte'        => 'alert',
            ]);
        yield ColorField::new('color', 'Couleur (optionnelle)')->setRequired(false)->hideOnIndex()
            ->setHelp('Prime sur la couleur du type.');
        yield ChoiceField::new('recipientType', 'Destinataires')
            ->setChoices([
                'Tout le monde'     => 'all',
                'Étudiants'         => 'student',
                'Accompagnateurs'   => 'accompagnateur',
                'Individuel'        => 'individual',
                'Classe'            => 'class',
            ]);
        yield TextField::new('recipientValue', 'Valeur destinataire')
            ->setRequired(false)
            ->setHelp('Pseudo ou e-mail pour "Individuel", code classe pour "Classe". Laisser vide sinon.');
        yield DateTimeField::new('scheduledAt', 'Envoi programmé')->setRequired(false)
            ->setHelp('Laisser vide pour envoyer manuellement.');
        yield DateTimeField::new('sentAt', 'Envoyé le')->onlyOnIndex()->setDisabled(true);
        yield DateTimeField::new('createdAt', 'Créé le')->onlyOnIndex()->setDisabled(true);
    }

    public function configureActions(Actions $actions): Actions
    {
        $send = Action::new('sendNow', 'Envoyer maintenant', 'fa fa-paper-plane')
            ->linkToCrudAction('sendNow')
            ->setCssClass('btn btn-success')
            ->displayIf(static fn(Notification $n) => !$n->isSent());

        return $actions
            ->add(Crud::PAGE_INDEX, $send)
            ->add(Crud::PAGE_DETAIL, $send);
    }

    #[AdminRoute(path: '/send-now', name: 'send_now')]
    public function sendNow(AdminContext $context): Response
    {
        /** @var Notification $notification */
        $notification = $context->getEntity()->getInstance();

        if ($this->send($notification)) {
            $this->addFlash('success', 'Notification envoyée.');
        } else {
            $this->addFlash('danger', 'Hub Mercure injoignable — notification non envoyée.');
        }

        return $this->redirect(
            $this->urlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl()
        );
    }


    private function send(Notification $notification): bool
    {
        if (!$this->notifier->publish($notification->getMercureTopic(), $notification->toMercurePayload())) {
            return false;
        }

        $notification->setSentAt(new \DateTimeImmutable());
        $this->em->flush();

        return true;
    }
}
