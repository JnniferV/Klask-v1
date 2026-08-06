<?php

namespace App\Service\Impl;

use App\Entity\User;
use App\Entity\UserSphereRating;
use App\Repository\SphereRepository;
use App\Repository\UserSphereRatingRepository;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias]
class UserServiceImpl implements UserService
{
    public function __construct(
        private readonly SphereRepository $sphereRepository,
        private readonly UserSphereRatingRepository $userSphereRatingRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Top 3 ET bottom 3 sphères en une seule requête
     * @return array{top: int[], bottom: int[]}
     */
    public function getTopAndBottomSphereIds(User $user): array
    {
        $ratings = $this->userSphereRatingRepository->findRatingsOrderedByScore($user);
        $ids     = array_column($ratings, 'sphereId');

        return [
            'top'    => array_slice($ids, 0, 3),
            'bottom' => array_slice($ids, -3),
        ];
    }

    public function hasCompletedQuestionnaire(User $user): bool
    {
        return $this->userSphereRatingRepository->findRatingsOrderedByScore($user) !== [];
    }

    /**
     * @param array<string, int> $zoneRatings
     * @return array<int, array{sphereId: int, rating: int}> trié par rating DESC
     */
    public function saveRatings(User $user, array $zoneRatings): array
    {
        $this->em->createQuery('DELETE FROM App\Entity\UserSphereRating r WHERE r.user = :user')
            ->setParameter('user', $user)
            ->execute();

        // 1 requête pour toutes les sphères
        $spheres = $this->sphereRepository->findBy(['name' => array_keys($zoneRatings)]);
        $result  = [];
        foreach ($spheres as $sphere) {
            $rating = $zoneRatings[$sphere->getName()];
            $this->em->persist(new UserSphereRating($user, $sphere, $rating));
            $result[] = ['sphereId' => $sphere->getId(), 'rating' => $rating];
        }

        usort($result, fn($a, $b) => $b['rating'] <=> $a['rating']);


        return $result;
    }
}
