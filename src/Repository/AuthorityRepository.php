<?php

namespace App\Repository;

use App\Entity\Authority;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Authority> */
class AuthorityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Authority::class);
    }

    // get* garantit
    public function getByRole(string $roleName): Authority
    {
        return $this->findByRole($roleName) ?? throw new \RuntimeException(sprintf('Autorité "%s" introuvable.', $roleName));
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
