<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Security\MercureTopics;

final readonly class NotificationReplay
{
    private const WINDOW_MINUTES = 60;

    public function __construct(
        private NotificationRepository $notifications,
        private MercureTopics $topics,
    ) {
    }

    /** @return array<int, array<string, mixed>> */
    public function forUser(?User $user): array
    {
        $abonnements = $this->topics->forUser($user);
        $since = new \DateTimeImmutable('-'.self::WINDOW_MINUTES.' minutes');

        $payloads = [];
        foreach ($this->notifications->findSentSince($since) as $notification) {
            if (in_array($notification->getMercureTopic(), $abonnements, true)) {
                $payloads[] = $notification->toMercurePayload();
            }
        }

        return $payloads;
    }
}
