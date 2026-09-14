<?php

namespace App\Entity;

use App\Repository\ParcoursRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParcoursRepository::class)]
#[ORM\UniqueConstraint(name: 'parcours_user_activity_uniq', columns: ['user_id', 'activity_id'])]
class Parcours
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Activity $activity;

    #[ORM\Column(options: ['default' => 0])]
    private int $priority = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $stepOrder = 0;

    #[ORM\Column]
    private \DateTimeImmutable $recommendedAt;

    public function __construct(User $user, Activity $activity, int $priority = 0, int $stepOrder = 0)
    {
        $this->user = $user;
        $this->activity = $activity;
        $this->priority = $priority;
        $this->stepOrder = $stepOrder;
        $this->recommendedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getActivity(): Activity
    {
        return $this->activity;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getStepOrder(): int
    {
        return $this->stepOrder;
    }
}
