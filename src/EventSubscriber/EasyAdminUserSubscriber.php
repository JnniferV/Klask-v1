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
            BeforeEntityPersistedEvent::class => ['sendCredentialsAndHashPassword'],
        ];
    }

    public function sendCredentialsAndHashPassword(BeforeEntityPersistedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if (!$entity instanceof User) {
            return;
        }
        $groupCode = $entity->getGroup()?->getCode() ?? 'Aucun groupe assigné';

        $plainPassword = $entity->getPassword();

        if ($plainPassword) {
            $email = (new Email())
                ->from('info@klask.app') // Mis à jour avec la bonne adresse
                ->to($entity->getEmail())
                ->subject('Vos identifiants KLASK')
                ->html("
                    <h1>Bienvenue sur KLASK !</h1>
                    <p>Voici vos identifiants de connexion :</p>
                    <ul>
                        <li><strong>Email :</strong> {$entity->getEmail()}</li>
                        <li><strong>Mot de passe :</strong> {$plainPassword}</li>
                        <li><strong>Code Groupe :</strong> {$groupCode}</li>
                    </ul>
                    <p>Bon événement !</p>
                ");

            $this->mailer->send($email);

            // On hache le mot de passe en clair récupéré
            $hashedPassword = $this->passwordHasher->hashPassword($entity, $plainPassword);

            // COn écrase le mot de passe en clair par le hachage

            $entity->setPassword($hashedPassword);
        }
    }
}
