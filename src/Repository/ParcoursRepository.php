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

    /** @return array<int, array{activityId: int, stepOrder: int, isAvailable: string, priority: int, sphereId: ?int}> */
    public function findOrderedByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            // sphereId sert à mesurer la charge de la sphère
            ->select('IDENTITY(p.activity) AS activityId', 'p.stepOrder', 'a.isAvailable', 'p.priority', 'IDENTITY(a.sphere) AS sphereId')
            ->join('p.activity', 'a')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.stepOrder', 'ASC')
            ->getQuery()
            ->getScalarResult();
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
