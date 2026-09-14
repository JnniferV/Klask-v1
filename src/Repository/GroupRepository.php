<?php

namespace App\Repository;

use App\Entity\Group;
use App\Security\RoleSecurity;
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

    public function findByCode(string $code): ?Group
    {
        return $this->findOneBy(['code' => strtoupper(trim($code))]);
    }

    // UPDATE atomique
    public function addScore(Group $group, int $points): void
    {
        $this->getEntityManager()->getConnection()->executeStatement(
            'UPDATE `group` SET score = COALESCE(score, 0) + ? WHERE id = ?',
            [$points, $group->getId()]
        );
        // refresh après UPDATE
        $this->getEntityManager()->refresh($group);
    }

    // hors accompagnateur
    public function countUsersByGroupId(int $groupId): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(u.id)')
            ->join('g.users', 'u')
            ->join('u.authority', 'a')
            ->where('g.id = :groupId')
            ->andWhere('a.authorityUser = :role')
            ->setParameter('groupId', $groupId)
            ->setParameter('role', RoleSecurity::STUDENT->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<array{name: mixed, code: mixed, score: mixed, studentCount: mixed}> */
    public function findTopGroups(int $limit = 5): array
    {
        /** @var list<array{name: mixed, code: mixed, score: mixed, studentCount: mixed}> */
        $rows = $this->createQueryBuilder('g')
            ->select('g.name', 'g.code', 'COALESCE(g.score, 0) AS score', 'COUNT(a.id) AS studentCount')
            ->leftJoin('g.users', 'u')
            ->leftJoin('u.authority', 'a', 'WITH', 'a.authorityUser = :role')
            ->groupBy('g.id')
            ->orderBy('score', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('role', RoleSecurity::STUDENT->value)
            ->getQuery()
            ->getScalarResult();

        return $rows;
    }
}
