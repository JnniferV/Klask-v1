<?php

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Notification> */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /** @return Notification[] */
    public function findPendingScheduled(): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.scheduledAt <= :now')
            ->andWhere('n.sentAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /** @return Notification[] */
    public function findSentSince(\DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.sentAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('n.sentAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
