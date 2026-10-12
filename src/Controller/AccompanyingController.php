<?php

namespace App\Controller;

use App\Entity\Group;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\NotificationReplay;
use App\Service\RealtimeNotifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ACCOMPANYING')]
class AccompanyingController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly RealtimeNotifier $notifier,
        private readonly NotificationReplay $replay,
    ) {
    }

    #[Route('/accompanying/poke/{id}', name: 'app_accompanying_poke', methods: ['POST'])]
    public function poke(int $id, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('poke', (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'Jeton CSRF invalide.'], 403);
        }

        $group = $this->currentGroup();

        if (null === $group) {
            return $this->json(['error' => 'Aucun groupe assigné.'], 400);
        }

        $student = $this->userRepository->findStudentInGroup($id, $group);

        if (null === $student) {
            return $this->json(['error' => 'Élève introuvable dans votre groupe.'], 404);
        }

        // gardé avant l'envoi, même si mercure est en panne
        $at = $this->replay->rememberPoke($student);
        $this->notifier->publish('poke/'.$student->getId(), ['poked' => true, 'at' => $at]);

        return $this->json(['ok' => true]);
    }

    private function currentGroup(): ?Group
    {
        $user = $this->getUser();

        return $user instanceof User ? $user->getGroup() : null;
    }
}
