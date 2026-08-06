<?php

namespace App\Repository;

use App\Entity\ActivityCategory;
use App\Entity\Sphere;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sphere>
 */
class SphereRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sphere::class);
    }

    /**
     * charge uniquement les stands (TYPE_STAND) par sphère
     * @return Sphere[]
     */
    public function findAllWithStands(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.activities', 'a')
            ->leftJoin('a.category', 'c')
            ->addSelect('a', 'c')
            ->andWhere('a.id IS NULL OR c.type = :type')
            ->setParameter('type', ActivityCategory::TYPE_STAND)
            ->getQuery()
            ->getResult();
    }
}
