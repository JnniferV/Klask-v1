<?php

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\ActivityCategory;
use App\Entity\Sphere;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Activity> */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    public function findOneByQrcodeToken(string $token): ?Activity
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')->addSelect('c')
            ->leftJoin('a.sphere', 's')->addSelect('s')
            ->where('a.qrcodeToken = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Activity[] */
    public function findStandsBySphere(Sphere|int $sphere): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.category', 'c')
            ->where('a.sphere = :sphere')
            ->andWhere('c.type = :type')
            ->setParameter('sphere', $sphere)
            ->setParameter('type', ActivityCategory::TYPE_STAND)
            ->getQuery()
            ->getResult();
    }

    /** @return Activity[] */
    public function findScheduledActivities(): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.category', 'c')
            ->addSelect('c')
            ->where('c.type IN (:types)')
            ->andWhere('c.beginningHourCategory IS NOT NULL')
            ->setParameter('types', ActivityCategory::SCHEDULED_TYPES)
            ->getQuery()
            ->getResult();
    }

    /** @return Activity[] */
    public function findStandaloneActivities(): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.category', 'c')
            ->addSelect('c')
            ->where('a.sphere IS NULL')
            ->andWhere('c.type != :stand')
            ->setParameter('stand', ActivityCategory::TYPE_STAND)
            ->getQuery()
            ->getResult();
    }

    /** @return array<int, bool> */
    public function findMapIdsByStandFlag(): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.id', 'c.type')
            ->join('a.category', 'c')
            ->where('(a.sphere IS NOT NULL AND c.type = :stand) OR (a.sphere IS NULL AND c.type <> :stand)')
            ->setParameter('stand', ActivityCategory::TYPE_STAND)
            ->getQuery()
            ->getScalarResult();

        return array_map(
            static fn (string $type): bool => ActivityCategory::TYPE_STAND === $type,
            array_column($rows, 'type', 'id')
        );
    }

    /**
     * @param int[] $sphereIds
     *
     * @return array<int, Activity[]>
     */
    public function findStandsBySpheres(array $sphereIds): array
    {
        if (!$sphereIds) {
            return [];
        }

        $activities = $this->createQueryBuilder('a')
            ->join('a.category', 'c')
            ->leftJoin('a.sphere', 's')
            ->addSelect('s')
            ->where('a.sphere IN (:spheres)')
            ->andWhere('c.type = :type')
            ->setParameter('spheres', $sphereIds)
            ->setParameter('type', ActivityCategory::TYPE_STAND)
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($activities as $activity) {
            $grouped[$activity->getSphere()->getId()][] = $activity;
        }

        return $grouped;
    }
}
