<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserSphereRating;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<UserSphereRating> */
class UserSphereRatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSphereRating::class);
    }

    /**
     * les sphères notées par l'élève, triées du meilleur au pire
     * @return array<int, array{sphereId: int, rating: int}>
     */
    public function findRatingsOrderedByScore(User $user): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.sphere) AS sphereId', 'r.rating')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.rating', 'DESC')
            ->getQuery()
            ->getScalarResult();

        return array_map(fn(array $row) => [
            'sphereId' => (int) $row['sphereId'],
            'rating'   => (int) $row['rating'],
        ], $rows);
    }
}
