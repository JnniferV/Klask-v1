<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'Cet e-mail est déjà utilisé.')]
#[UniqueEntity(fields: ['pseudo'], message: 'Ce nom est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $pseudo = null;

    #[ORM\Column(length: 180, unique: true, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $blockedUntil = null;

    // Doctrine: pas constructeur
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $invalidScanCount = 0;

    #[ORM\Column(nullable: true)]
    private ?int $score = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Authority $authority = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Group $group = null;

    /**
     * @var Collection<int, Scan>
     */
    #[ORM\OneToMany(targetEntity: Scan::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $scans;

    public function __construct()
    {
        $this->scans = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->pseudo ?? $this->email ?? 'User #'.$this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->email ?? $this->pseudo ?? '';
    }

    // préfixe ROLE_
    public function getRoles(): array
    {
        $roles = [];

        foreach ($this->authority?->getAuthorityRoles() ?? [] as $authorityRole) {
            $roles[] = 'ROLE_'.$authorityRole->getRole()->getNameRole();
        }

        return $roles ?: ['ROLE_STUDENT'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(?string $pseudo): static
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getBlockedUntil(): ?\DateTimeImmutable
    {
        return $this->blockedUntil;
    }

    public function creditPoints(int $points): static
    {
        $this->score = ($this->score ?? 0) + $points;
        $this->invalidScanCount = 0;

        return $this;
    }

    /** @return bool */
    public function registerInvalidScan(int $threshold, int $blockMinutes): bool
    {
        ++$this->invalidScanCount;

        if ($this->invalidScanCount < $threshold) {
            return false;
        }

        $this->blockedUntil = new \DateTimeImmutable('+'.$blockMinutes.' minutes');
        $this->invalidScanCount = 0;

        return true;
    }

    public function isBlocked(): bool
    {
        return null !== $this->blockedUntil && $this->blockedUntil > new \DateTimeImmutable();
    }

    public function setBlockedUntil(?\DateTimeImmutable $blockedUntil): static
    {
        $this->blockedUntil = $blockedUntil;

        return $this;
    }

    public function getInvalidScanCount(): int
    {
        return $this->invalidScanCount;
    }

    public function setInvalidScanCount(int $invalidScanCount): static
    {
        $this->invalidScanCount = $invalidScanCount;

        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getAuthority(): ?Authority
    {
        return $this->authority;
    }

    public function setAuthority(?Authority $authority): static
    {
        $this->authority = $authority;

        return $this;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(?Group $group): static
    {
        $this->group = $group;

        return $this;
    }
}
