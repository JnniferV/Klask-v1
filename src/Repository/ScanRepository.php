<?php

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Group;
use App\Entity\Scan;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Scan> */
class ScanRepository extends ServiceEntityRepository
{
    public const OCCUPANCY_WINDOW_MIN = 10;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Scan::class);
    }

    public function countRecentForActivity(Activity $activity): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.activity = :activity')
            ->andWhere('s.hourValidation >= :since')
            ->setParameter('activity', $activity)
            ->setParameter('since', new \DateTimeImmutable('-'.self::OCCUPANCY_WINDOW_MIN.' minutes'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<int, int> */
    public function countRecentGroupedByActivity(): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.activity) AS activityId', 'COUNT(s.id) AS cnt')
            ->where('s.hourValidation >= :since')
            ->setParameter('since', new \DateTimeImmutable('-'.self::OCCUPANCY_WINDOW_MIN.' minutes'))
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

    /** @return int[] */
    public function findActivityIdsByUser(User $user): array
    {
        $rows = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.activity) AS activityId')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.hourValidation', 'DESC')
            ->getQuery()
            ->getScalarResult();

        return array_map(fn (array $r) => (int) $r['activityId'], $rows);
    }

    /** @return list<array{sphere: mixed, visits: mixed}> */
    public function findMostVisitedSpheres(): array
    {
        /** @var list<array{sphere: mixed, visits: mixed}> */
        $rows = $this->createQueryBuilder('s')
            ->select('sp.name AS sphere', 'COUNT(s.id) AS visits')
            ->join('s.activity', 'a')
            ->join('a.sphere', 'sp')
            ->groupBy('sp.id')
            ->orderBy('visits', 'DESC')
            ->getQuery()
            ->getScalarResult();

        return $rows;
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<array{activity: mixed, visits: mixed}> */
    public function findTopActivities(int $limit = 10): array
    {
        /** @var list<array{activity: mixed, visits: mixed}> */
        $rows = $this->createQueryBuilder('s')
            ->select('a.name AS activity', 'COUNT(s.id) AS visits')
            ->join('s.activity', 'a')
            ->groupBy('a.id')
            ->orderBy('visits', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return $rows;
    }

    public function countInternshipScans(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->join('s.activity', 'a')
            ->where('a.isInternship = true')
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

        return null !== $value ? new \DateTimeImmutable((string) $value) : null;
    }

    public function findFirstAt(User $user): ?\DateTimeImmutable
    {
        $value = $this->createQueryBuilder('s')
            ->select('MIN(s.hourValidation)')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $value ? new \DateTimeImmutable((string) $value) : null;
    }

    /** @return Scan[] */
    public function findByUserWithDetails(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('a', 'c', 'sp')
            ->join('s.activity', 'a')
            ->join('a.category', 'c')
            ->leftJoin('a.sphere', 'sp')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('s.hourValidation', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<array{sphereName: string, activityName: string, activityDescription: ?string, categoryType: string}> */
    public function findGroupScanRows(Group $group): array
    {
        /** @var list<array{sphereName: string, activityName: string, activityDescription: ?string, categoryType: string}> $rows */
        $rows = $this->createQueryBuilder('s')
            ->select(
                'COALESCE(sp.name, :unclassified) AS sphereName',
                'a.name AS activityName',
                'a.description AS activityDescription',
                'c.type AS categoryType',
            )
            ->join('s.user', 'u')
            ->join('s.activity', 'a')
            ->join('a.category', 'c')
            ->leftJoin('a.sphere', 'sp')
            ->where('u.group = :group')
            ->setParameter('group', $group)
            ->setParameter('unclassified', 'Non classé')
            ->getQuery()
            ->getArrayResult();

        return $rows;
    }
}
