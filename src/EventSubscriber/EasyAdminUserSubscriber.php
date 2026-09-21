<?php

namespace App\EventSubscriber;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class EasyAdminUserSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // On écoute l'événement "Juste avant d'enregistrer en BDD" d'EasyAdmin
            BeforeEntityPersistedEvent::class => ['sendCredentialsAndHashPassword'],
        ];
    }

    public function sendCredentialsAndHashPassword(BeforeEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        // On vérifie que l'entité créée est bien un Utilisateur (Accompagnateur)
        if (!$entity instanceof User) {
            return;
        }

        $plainPassword = $entity->getPlainPassword();

        // Si un mot de passe en clair a été saisi
        if ($plainPassword) {

            // ENVOI DE L'EMAIL (Avant le hachage !)
            $email = (new Email())
                ->from('info@klask.app')
                ->to($entity->getEmail())
                ->subject('Vos identifiants KLASK')
                ->html("
                    <h1>Bienvenue sur KLASK !</h1>
                    <p>Voici vos identifiants de connexion :</p>
                    <ul>
                        <li><strong>Email :</strong> {$entity->getEmail()}</li>
                        <li><strong>Mot de passe :</strong> {$plainPassword}</li>
                        <li><strong>Code Groupe :</strong> {$entity->getGroupCode()}</li>
                    </ul>
                    <p>Bon événement !</p>
                ");

            $this->mailer->send($email);

            //  HACHAGE DU MOT DE PASSE (Pour la sécurité de la BDD)
            $hashedPassword = $this->passwordHasher->hashPassword($entity, $plainPassword);
            $entity->setPassword($hashedPassword);

            // On efface le mot de passe en clair de la mémoire par sécurité
            $entity->setPlainPassword(null);
        }
    }
}
