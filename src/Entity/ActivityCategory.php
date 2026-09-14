<?php

namespace App\Entity;

use App\Repository\ActivityCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityCategoryRepository::class)]
class ActivityCategory
{
    public const TYPE_STAND = 'Stand';
    public const TYPE_ATELIER = 'Atelier';
    public const TYPE_CONFERENCE = 'Conférence';
    public const TYPE_PROFESSION = 'Profession';

    public const SCHEDULED_TYPES = [self::TYPE_ATELIER, self::TYPE_CONFERENCE];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $type;

    #[ORM\Column(options: ['unsigned' => true])]
    private int $nbrPoints;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $beginningHourCategory = null;

    /**
     * @var Collection<int, Activity>
     */
    #[ORM\OneToMany(targetEntity: Activity::class, mappedBy: 'category', orphanRemoval: true)]
    private Collection $activities;

    public function __construct()
    {
        $this->activities = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isStand(): bool
    {
        return self::TYPE_STAND === $this->type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function __toString(): string
    {
        return $this->type;
    }

    public function getNbrPoints(): int
    {
        return $this->nbrPoints;
    }

    public function setNbrPoints(int $nbrPoints): static
    {
        $this->nbrPoints = $nbrPoints;

        return $this;
    }

    public function getBeginningHourCategory(): ?\DateTimeImmutable
    {
        return $this->beginningHourCategory;
    }

    public function setBeginningHourCategory(?\DateTimeImmutable $beginningHourCategory): static
    {
        $this->beginningHourCategory = $beginningHourCategory;

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
            $activity->setCategory($this);
        }

        return $this;
    }

    public function removeActivity(Activity $activity): static
    {
        if ($this->activities->removeElement($activity) && $activity->getCategory() === $this) {
            $activity->setCategory(null);
        }

        return $this;
    }
}
