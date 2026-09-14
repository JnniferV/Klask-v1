<?php

namespace App\Dto;

use App\Entity\Group;
use App\Entity\Scan;
use App\Entity\User;

final readonly class BilanEleve
{
    /** @param array<Scan> $scans */
    public function __construct(
        public User $user,
        public ?Group $group,
        public array $scans,
        public int $scorePerso,
        public int $scoreGroupe,
        public bool $isPdfUnlocked,
    ) {
    }
}
