<?php

namespace App\Dto;

final readonly class ActiviteVisitee
{
    public function __construct(
        public string $category,
        public ?string $description,
        public int $count,
    ) {
    }

    public function avecUnEleveDePlus(): self
    {
        return new self($this->category, $this->description, $this->count + 1);
    }
}
