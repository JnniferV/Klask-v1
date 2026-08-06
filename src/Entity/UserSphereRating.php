<?php

namespace App\Entity;

use App\Repository\UserSphereRatingRepository;
use Doctrine\ORM\Mapping as ORM;

// Note de l'élève pour avoir les 3 sphères préférées (et les 3 secondaires), il note de 1 à 6 les sphères
 // Clé composite (user + sphere) : un élève ne peut noter qu'une fois une sphère
#[ORM\Entity(repositoryClass: UserSphereRatingRepository::class)]
class UserSphereRating
{
    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Id]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Sphere $sphere;

    #[ORM\Column]
    private int $rating;

    public function __construct(User $user, Sphere $sphere, int $rating)
    {
        $this->user   = $user;
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
