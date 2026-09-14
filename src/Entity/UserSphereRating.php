<?php

namespace App\Entity;

use App\Repository\UserSphereRatingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserSphereRatingRepository::class)]
class UserSphereRating
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Sphere $sphere;

    #[ORM\Column]
    private int $rating;

    public function __construct(User $user, Sphere $sphere, int $rating)
    {
        $this->user = $user;
        $this->sphere = $sphere;
        $this->rating = $rating;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getSphere(): Sphere
    {
        return $this->sphere;
    }

    public function getRating(): int
    {
        return $this->rating;
    }
}
