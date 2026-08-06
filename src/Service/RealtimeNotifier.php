<?php

namespace App\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

//publie une mise à jour temps réel via Mercure

final class RealtimeNotifier
{
    public function __construct(private readonly HubInterface $hub) {}

    /** @return bool false si le hub est indispo */
    public function publish(string $topic, array $payload): bool
    {
        try {
            $this->hub->publish(new Update($topic, json_encode($payload)));

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
