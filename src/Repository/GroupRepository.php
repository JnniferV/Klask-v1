<?php

namespace App\Repository;

use App\Entity\Group;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Group>
 */
class GroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Group::class);
    }

    // niveaux distincts en bdd pour le select du formulaire d'inscription
    /** @return string[] */
    public function findDistinctLevels(): array
    {
        $rows = $this->createQueryBuilder('g')
            ->select('DISTINCT g.name')
            ->where('g.name IS NOT NULL')
            ->orderBy('g.name', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'name');
    }

    // groupe par code unique saisi à l'inscription
    public function findByCode(string $code): ?Group
    {
        return $this->findOneBy(['code' => strtoupper(trim($code))]);
    }

    public function countUsersByGroupId(int $groupId): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(u.id)')
            ->join('g.users', 'u')
            ->where('g.id = :groupId')
            ->setParameter('groupId', $groupId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findTopGroups(int $limit = 5): array
    {
        return $this->createQueryBuilder('g')
            ->select('g.name', 'g.code', 'COALESCE(g.score, 0) AS score', 'COUNT(u.id) AS studentCount')
            ->leftJoin('g.users', 'u')
            ->groupBy('g.id')
            ->orderBy('score', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();
    }
}
