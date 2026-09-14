<?php

namespace App\Dto;

use App\Entity\Group;

final readonly class BilanGroupe
{
    /**
     * @param array<string, int> $spheresStats
     * @param array<string, ActiviteVisitee> $activitiesStats
     */
    public function __construct(
        public bool $hasGroup,
        public ?Group $group,
        public int $studentsCount,
        public int $totalScore,
        public int $totalScans,
        public array $spheresStats,
        public array $activitiesStats,
        public bool $isPdfUnlocked,
    ) {
    }

    public static function sansGroupe(): self
    {
        return new self(false, null, 0, 0, 0, [], [], false);
    }
}
