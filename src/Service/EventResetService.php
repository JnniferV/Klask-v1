<?php

namespace App\Service;

use App\Entity\Event;
use Doctrine\DBAL\Connection;

final class EventResetService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, int> */
    public function reset(Event $event): array
    {
        $eventId = (int) $event->getId();

        $counts = [
            'Élèves supprimés' => (int) $this->connection->executeStatement(
                "DELETE u FROM user u INNER JOIN `group` g ON g.id = u.group_id INNER JOIN authority a ON a.id = u.authority_id WHERE g.event_id = ? AND a.authority_user = 'STUDENT'",
                [$eventId]
            ),
            'Scores groupes remis à 0' => (int) $this->connection->executeStatement(
                'UPDATE `group` SET score = 0 WHERE event_id = ?',
                [$eventId]
            ),
        ];

        $event->setResetAt(new \DateTimeImmutable());

        return $counts;
    }
}
