<?php

namespace App\Repository;

use App\Entity\Authority;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuthorityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Authority::class);
    }

    public function findByRole(string $roleName): ?Authority
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.authorityRoles', 'ar')
            ->innerJoin('ar.role', 'r')
            ->andWhere('r.nameRole = :roleName')
            ->setParameter('roleName', $roleName)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
