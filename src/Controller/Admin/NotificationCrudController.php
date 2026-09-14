<?php

namespace App\Controller\Admin;

use App\Entity\Establishment;
use App\Entity\Notification;
use App\Repository\EstablishmentRepository;
use App\Service\RealtimeNotifier;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

/** @extends AbstractCrudController<Notification> */
class NotificationCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly RealtimeNotifier $notifier,
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $urlGenerator,
        private readonly EstablishmentRepository $establishments,
    ) {
    }

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

        if ($entity instanceof Notification && null === $entity->getScheduledAt()) {
            $this->send($entity);
        }
    }

    // retirer la date programmée d'une notif jamais envoyée déclenche l'envoi
    public function updateEntity(EntityManagerInterface $em, mixed $entity): void
    {
        parent::updateEntity($em, $entity);

        if ($entity instanceof Notification && null === $entity->getScheduledAt() && !$entity->isSent()) {
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
            ->setHelp('Détermine la couleur de la bannière : bleu, vert, orange, rouge.')
            ->setChoices([
                'Information' => 'info',
                'Succès' => 'success',
                'Avertissement' => 'warning',
                'Alerte' => 'alert',
            ]);
        yield ChoiceField::new('recipientType', 'Destinataires')
            ->setChoices([
                'Tout le monde' => 'all',
                'Étudiants' => 'student',
                'Accompagnateurs' => 'accompagnateur',
                'Classe' => 'class',
                'Établissement' => 'establishment',
            ]);
        yield TextField::new('recipientValue', 'Valeur destinataire')
            ->setRequired(false)
            ->setHelp('Code classe pour « Classe », ID pour « Établissement » ('.$this->establishmentList().'). Laisser vide sinon.');
        yield DateTimeField::new('scheduledAt', 'Envoi programmé')->setRequired(false)
            ->setHelp('Laisser vide pour envoyer manuellement.');
        yield DateTimeField::new('sentAt', 'Envoyé le')->onlyOnIndex()->setDisabled(true);
        yield DateTimeField::new('createdAt', 'Créé le')->onlyOnIndex()->setDisabled(true);
    }

    // rappel « 3 = Collège X », l'admin saisit un id
    // htmlspecialchars : EasyAdmin rend l'aide en html brut
    private function establishmentList(): string
    {
        return implode(', ', array_map(
            static fn (Establishment $e) => $e->getId().' = '.htmlspecialchars($e->getName(), \ENT_QUOTES),
            $this->establishments->findBy([], ['id' => 'ASC'])
        ));
    }

    public function configureActions(Actions $actions): Actions
    {
        $send = Action::new('sendNow', 'Envoyer maintenant', 'fa fa-paper-plane')
            ->linkToCrudAction('sendNow')
            ->setCssClass('btn btn-success')
            ->displayIf(static fn (Notification $n) => !$n->isSent());

        return $actions
            ->add(Crud::PAGE_INDEX, $send)
            ->add(Crud::PAGE_DETAIL, $send);
    }

    /** @param AdminContext<Notification> $context */
    #[AdminRoute(path: '/send-now', name: 'send_now')]
    public function sendNow(AdminContext $context): Response
    {
        /** @var Notification $notification */
        $notification = $context->getEntity()->getInstance();
        $this->send($notification);

        return $this->redirect(
            $this->urlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl()
        );
    }

    // sans sentAt, ni direct ni rejeu : l'échec doit se voir
    private function send(Notification $notification): void
    {
        if (!$this->notifier->publish($notification->getMercureTopic(), $notification->toMercurePayload())) {
            $this->addFlash('danger', 'Hub Mercure injoignable — notification NON envoyée. Réessayez avec « Envoyer maintenant ».');

            return;
        }

        $notification->setSentAt(new \DateTimeImmutable());
        $this->em->flush();
        $this->addFlash('success', 'Notification envoyée.');
    }
}
