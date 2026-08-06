<?php

namespace App\Repository;

use App\Entity\Parcours;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Parcours> */
class ParcoursRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Parcours::class);
    }

    /**
     * parcours ordonné : IDs activités + stepOrder + isAvailable, triés par étape
     * @return array<int, array{activityId: int, stepOrder: int, isAvailable: string}>
     */
    public function findOrderedByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->select('IDENTITY(p.activity) AS activityId', 'p.stepOrder', 'a.isAvailable')
            ->join('p.activity', 'a')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.stepOrder', 'ASC')
            ->getQuery()
            ->getScalarResult();
    }

    /**
     * nombre de students à chaque sphère à une étape donnée pour éviter attente
     * @param int[] $sphereIds
     * @return array<int, int> [sphereId => count]
     */
    public function countStudentsPerSphereAtStep(array $sphereIds, int $step): array
    {
        if (empty($sphereIds)) {
            return [];
        }

        $rows = $this->createQueryBuilder('p')
            ->select('IDENTITY(a.sphere) AS sphereId', 'COUNT(p.id) AS cnt')
            ->join('p.activity', 'a')
            ->where('p.stepOrder = :step')
            ->andWhere('a.sphere IN (:sids)')
            ->setParameter('step', $step)
            ->setParameter('sids', $sphereIds)
            ->groupBy('a.sphere')
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'cnt', 'sphereId');
    }

    public function deleteByUser(User $user): void
    {
        $this->createQueryBuilder('p')
            ->delete()
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
