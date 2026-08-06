<?php

namespace App\Entity;

use App\Repository\EstablishmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EstablishmentRepository::class)]
class Establishment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name;

    /**
     * @var Collection<int, Group>
     */
    // cascade persist : les classes ajoutées depuis le formulaire Établissement sont enregistrées avec lui
    #[ORM\OneToMany(targetEntity: Group::class, mappedBy: 'establishment', cascade: ['persist'], orphanRemoval: true)]
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
            $group->setEstablishment($this);
        }

        return $this;
    }

    public function removeGroup(Group $group): static
    {
        if ($this->groups->removeElement($group) && $group->getEstablishment() === $this) {
            $group->setEstablishment(null);
        }

        return $this;
    }
}
