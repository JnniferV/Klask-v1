<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Security\MercureTopics;
use Psr\Cache\CacheItemPoolInterface;

final readonly class NotificationReplay
{
    private const WINDOW_MINUTES = 60;

    public function __construct(
        private NotificationRepository $notifications,
        private MercureTopics $topics,
        private CacheItemPoolInterface $cache,
    ) {
    }

    // mercure perd le signal si l'onglet dort, on le garde
    public function rememberPoke(User $student): int
    {
        $at = time();
        $this->cache->save($this->cache->getItem(self::pokeKey($student))->set($at)->expiresAfter(self::WINDOW_MINUTES * 60));

        return $at;
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

        // renvoyé tant que l'élève ne l'a pas fermé
        if (null !== $user && is_int($pokedAt = $this->cache->getItem(self::pokeKey($user))->get())) {
            $payloads[] = ['poked' => true, 'at' => $pokedAt];
        }

        return $payloads;
    }

    private static function pokeKey(User $user): string
    {
        return 'poke_'.$user->getId();
    }
}
