<?php

namespace App\Entity;

use App\Repository\AuthorityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthorityRepository::class)]
class Authority
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $authorityUser;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'authority')]
    private Collection $users;

    /**
     * @var Collection<int, AuthorityRole>
     */
    #[ORM\OneToMany(targetEntity: AuthorityRole::class, mappedBy: 'authority', orphanRemoval: true)]
    private Collection $authorityRoles;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->authorityRoles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAuthorityUser(): string
    {
        return $this->authorityUser;
    }

    public function setAuthorityUser(string $authorityUser): static
    {
        $this->authorityUser = $authorityUser;

        return $this;
    }

    public function __toString(): string
    {
        return $this->authorityUser;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setAuthority($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user) && $user->getAuthority() === $this) {
            $user->setAuthority(null);
        }

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
