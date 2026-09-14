<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class MercureTopics
{
    public function __construct(private Security $security)
    {
    }

    /** @return string[] */
    public function forUser(?User $user): array
    {
        $topics = ['map-update', 'event-alert'];

        if (null === $user) {
            return $topics;
        }

        $code = $user->getGroup()?->getCode();

        if ($this->security->isGranted('ROLE_STUDENT')) {
            $topics[] = 'poke/'.$user->getId();
            $topics[] = 'event-alert/student';
            $topics[] = 'event-alert/user/'.$user->getPseudo();
            if (null !== $code) {
                $topics[] = 'event-alert/class/'.$code;
                // pas group-score
                $topics[] = 'group-total/'.$code;
            }
        } elseif ($this->security->isGranted('ROLE_ACCOMPANYING')) {
            $topics[] = 'event-alert/accompagnateur';
            $topics[] = 'event-alert/user/'.$user->getEmail();
            if (null !== $code) {
                $topics[] = 'group-score/'.$code;
                $topics[] = 'event-alert/class/'.$code;
            }
        }

        // commun aux deux rôles, notif par établissement
        $establishment = $user->getGroup()?->getEstablishment();
        if (null !== $establishment) {
            $topics[] = 'event-alert/establishment/'.$establishment->getId();
        }

        return $topics;
    }
}
