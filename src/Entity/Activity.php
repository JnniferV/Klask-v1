<?php

namespace App\Entity;

use App\Repository\ActivityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[UniqueEntity(fields: ['name'], message: 'Une activité porte déjà ce nom.')]
#[Assert\Expression('not this.isStand() or this.getSphere()', message: 'Un Stand doit être rattaché à une sphère.')]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $name;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $qrcode = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $qrcodeToken = null;

    #[ORM\Column(nullable: true)]
    private ?float $pointX = null;

    #[ORM\Column(nullable: true)]
    private ?float $pointY = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $softLimit = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $hardLimit = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $isInternship = false;

    #[ORM\Column(options: ['default' => true])]
    private bool $isAvailable = true;

    #[ORM\Column(nullable: true)]
    private ?int $estimatedWaitMinutes = null;

    // onDelete SET NULL
    #[ORM\ManyToOne(inversedBy: 'activities')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Sphere $sphere = null;

    #[ORM\ManyToOne(inversedBy: 'activities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ActivityCategory $category = null;

    /**
     * @var Collection<int, Scan>
     */
    #[ORM\OneToMany(targetEntity: Scan::class, mappedBy: 'activity')]
    private Collection $scans;

    public function __construct()
    {
        $this->scans = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isStand(): bool
    {
        return $this->category?->isStand() ?? false;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getQrcode(): ?string
    {
        return $this->qrcode;
    }

    public function setQrcode(?string $qrcode): static
    {
        $this->qrcode = $qrcode;

        return $this;
    }

    public function getQrcodeToken(): ?string
    {
        return $this->qrcodeToken;
    }

    public function setQrcodeToken(?string $qrcodeToken): static
    {
        $this->qrcodeToken = $qrcodeToken;

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

    public function getSoftLimit(): int
    {
        return $this->softLimit;
    }

    public function setSoftLimit(int $softLimit): static
    {
        $this->softLimit = $softLimit;

        return $this;
    }

    public function getHardLimit(): int
    {
        return $this->hardLimit;
    }

    public function setHardLimit(int $hardLimit): static
    {
        $this->hardLimit = $hardLimit;

        return $this;
    }

    public function isInternship(): bool
    {
        return $this->isInternship;
    }

    public function setIsInternship(bool $isInternship): static
    {
        $this->isInternship = $isInternship;

        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): static
    {
        $this->isAvailable = $isAvailable;

        return $this;
    }

    public function getEstimatedWaitMinutes(): ?int
    {
        return $this->estimatedWaitMinutes;
    }

    public function setEstimatedWaitMinutes(?int $estimatedWaitMinutes): static
    {
        $this->estimatedWaitMinutes = $estimatedWaitMinutes;

        return $this;
    }

    public function getSphere(): ?Sphere
    {
        return $this->sphere;
    }

    public function setSphere(?Sphere $sphere): static
    {
        $this->sphere = $sphere;

        return $this;
    }

    public function getCategory(): ?ActivityCategory
    {
        return $this->category;
    }

    public function setCategory(?ActivityCategory $category): static
    {
        $this->category = $category;

        return $this;
    }
}
