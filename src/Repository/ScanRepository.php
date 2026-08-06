<?php

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Scan;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Scan> */
class ScanRepository extends ServiceEntityRepository
{
    // Fenêtre (min) des scans récents
    public const OCCUPANCY_WINDOW_MIN = 10;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Scan::class);
    }

    // scans récents d'une activité pour calculer attente
    public function countRecentForActivity(Activity $activity): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.activity = :activity')
            ->andWhere('s.hourValidation >= :since')
            ->setParameter('activity', $activity)
            ->setParameter('since', new \DateTimeImmutable('-' . self::OCCUPANCY_WINDOW_MIN . ' minutes'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * ccans récents groupés par activité :indicateur « presque plein / plein » de la carte
     * @return array<int, int> [activityId => nb scans récents]
     */
    public function countRecentGroupedByActivity(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.activity) AS activityId', 'COUNT(s.id) AS cnt')
            ->where('s.hourValidation >= :since')
            ->setParameter('since', new \DateTimeImmutable('-' . self::OCCUPANCY_WINDOW_MIN . ' minutes'))
            ->groupBy('s.activity')
            ->getQuery()
            ->getScalarResult();

        return array_map('intval', array_column($rows, 'cnt', 'activityId'));
    }

    public function existsForUserAndActivity(User $user, Activity $activity): bool
    {
        return (bool) $this->createQueryBuilder('s')
            ->select('1')
            ->where('s.user = :user')
            ->andWhere('s.activity = :activity')
            ->setParameter('user', $user)
            ->setParameter('activity', $activity)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * ID des activités scannées par l'élève, du plus récent au plus ancien
     * @return int[]
     */
    public function findActivityIdsByUser(User $user): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.activity) AS activityId')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.hourValidation', 'DESC')
            ->getQuery()
            ->getScalarResult();

        return array_map(fn(array $r) => (int) $r['activityId'], $rows);
    }

    //sphères les plus visitées
    public function findMostVisitedSpheres(): array
    {
        return $this->createQueryBuilder('s')
            ->select('sp.name AS sphere', 'COUNT(s.id) AS visits')
            ->join('s.activity', 'a')
            ->join('a.sphere', 'sp')
            ->groupBy('sp.id')
            ->orderBy('visits', 'DESC')
            ->getQuery()
            ->getScalarResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLastAt(User $user): ?\DateTimeImmutable
    {
        $value = $this->createQueryBuilder('s')
            ->select('MAX(s.hourValidation)')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $value !== null ? new \DateTimeImmutable($value) : null;
    }
}
