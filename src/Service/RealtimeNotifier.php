<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class RealtimeNotifier
{
    public function __construct(
        private readonly HubInterface $hub,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return bool
     */
    public function publish(string $topic, array $payload): bool
    {
        try {
            $this->hub->publish(new Update($topic, json_encode($payload, \JSON_THROW_ON_ERROR), true));

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Mercure : publication échouée sur {topic}', ['topic' => $topic, 'exception' => $e]);

            return false;
        }
    }
}
