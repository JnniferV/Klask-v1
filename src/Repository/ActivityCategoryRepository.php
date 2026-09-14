<?php

namespace App\Repository;

use App\Entity\ActivityCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ActivityCategory> */
class ActivityCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityCategory::class);
    }

    /** @return ActivityCategory[] */
    public function findScheduledWithHour(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.activities', 'a')->addSelect('a')
            ->where('c.type IN (:types)')
            ->andWhere('c.beginningHourCategory IS NOT NULL')
            ->setParameter('types', ActivityCategory::SCHEDULED_TYPES)
            ->getQuery()
            ->getResult();
    }
}
