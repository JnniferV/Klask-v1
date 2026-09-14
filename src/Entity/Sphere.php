<?php

namespace App\Entity;

use App\Repository\SphereRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SphereRepository::class)]
class Sphere
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $name;

    #[ORM\Column(length: 50, unique: true)]
    private string $color;

    #[ORM\Column(nullable: true)]
    private ?float $pointX = null;

    #[ORM\Column(nullable: true)]
    private ?float $pointY = null;

    #[ORM\Column]
    private float $radius = 10.0;

    /**
     * @var Collection<int, Activity>
     */
    #[ORM\OneToMany(targetEntity: Activity::class, mappedBy: 'sphere')]
    private Collection $activities;

    public function __construct()
    {
        $this->activities = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getPointX(): ?float
    {
        return $this->pointX;
    }

    public function setPointX(?float $pointX): static
    {
        $this->pointX = $pointX;

        return $this;
    }

    public function getPointY(): ?float
    {
        return $this->pointY;
    }

    public function setPointY(?float $pointY): static
    {
        $this->pointY = $pointY;

        return $this;
    }

    public function getRadius(): float
    {
        return $this->radius;
    }

    public function setRadius(float $radius): static
    {
        $this->radius = $radius;

        return $this;
    }

    /**
     * @return Collection<int, Activity>
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function addActivity(Activity $activity): static
    {
        if (!$this->activities->contains($activity)) {
            $this->activities->add($activity);
            $activity->setSphere($this);
        }

        return $this;
    }

    public function removeActivity(Activity $activity): static
    {
        if ($this->activities->removeElement($activity) && $activity->getSphere() === $this) {
            $activity->setSphere(null);
        }

        return $this;
    }
}
