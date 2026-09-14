<?php

namespace App\Entity;

use App\Repository\AppParameterRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppParameterRepository::class)]
class AppParameter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $paramKey;

    #[ORM\Column(length: 500)]
    private string $paramValue;

    #[ORM\Column(length: 20, options: ['default' => 'string'])]
    private string $paramType = 'string';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParamKey(): string
    {
        return $this->paramKey;
    }

    public function setParamKey(string $paramKey): static
    {
        $this->paramKey = $paramKey;

        return $this;
    }

    public function getParamValue(): string
    {
        return $this->paramValue;
    }

    public function setParamValue(string $paramValue): static
    {
        $this->paramValue = $paramValue;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getParamType(): string
    {
        return $this->paramType;
    }

    public function setParamType(string $paramType): static
    {
        $this->paramType = $paramType;

        return $this;
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCastedValue(): int|float|bool|string
    {
        return match ($this->paramType) {
            'integer' => (int) $this->paramValue,
            'float' => (float) $this->paramValue,
            'boolean' => filter_var($this->paramValue, FILTER_VALIDATE_BOOLEAN),
            default => $this->paramValue,
        };
    }
}
