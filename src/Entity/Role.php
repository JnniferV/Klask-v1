<?php

namespace App\Entity;

use App\Repository\RoleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $nameRole;

    /**
     * @var Collection<int, AuthorityRole>
     */
    #[ORM\OneToMany(targetEntity: AuthorityRole::class, mappedBy: 'role')]
    private Collection $authorityRoles;

    public function __construct()
    {
        $this->authorityRoles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNameRole(): string
    {
        return $this->nameRole;
    }

    public function setNameRole(string $nameRole): static
    {
        $this->nameRole = $nameRole;

        return $this;
    }

    /**
     * @return Collection<int, AuthorityRole>
     */
    public function getAuthorityRoles(): Collection
    {
        return $this->authorityRoles;
    }
}
