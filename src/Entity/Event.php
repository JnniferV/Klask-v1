<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $name;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $beginningHourEvent = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endHourEvent = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $resetAt = null;

    /**
     * @var Collection<int, Group>
     */
    #[ORM\OneToMany(targetEntity: Group::class, mappedBy: 'event')]
    private Collection $groups;

    public function __construct()
    {
        $this->groups = new ArrayCollection();
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

    public function getBeginningHourEvent(): ?\DateTimeImmutable
    {
        return $this->beginningHourEvent;
    }

    public function setBeginningHourEvent(?\DateTimeImmutable $beginningHourEvent): static
    {
        $this->beginningHourEvent = $beginningHourEvent;

        return $this;
    }

    public function getEndHourEvent(): ?\DateTimeImmutable
    {
        return $this->endHourEvent;
    }

    public function setEndHourEvent(?\DateTimeImmutable $endHourEvent): static
    {
        $this->endHourEvent = $endHourEvent;

        return $this;
    }

    public function getResetAt(): ?\DateTimeImmutable
    {
        return $this->resetAt;
    }

    public function setResetAt(?\DateTimeImmutable $resetAt): static
    {
        $this->resetAt = $resetAt;

        return $this;
    }

    public function isReset(): bool
    {
        return $this->resetAt !== null;
    }

    /**
     * @return Collection<int, Group>
     */
    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function addGroup(Group $group): static
    {
        if (!$this->groups->contains($group)) {
            $this->groups->add($group);
            $group->setEvent($this);
        }

        return $this;
    }

    public function removeGroup(Group $group): static
    {
        if ($this->groups->removeElement($group) && $group->getEvent() === $this) {
            $group->setEvent(null);
        }

        return $this;
    }
}
