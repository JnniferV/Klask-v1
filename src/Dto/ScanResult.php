<?php

namespace App\Dto;

final readonly class ScanResult
{
    private function __construct(
        public bool $ok,
        public ?string $code,
        public ?string $error,
        public int $points,
        public int $bonus,
        public string $activityName,
        public int $activityId,
        public int $userScore,
        public int $groupScore,
        // prochaine étape choisie par le serveur, 0 si aucune
        public int $nextStepId = 0,
    ) {
    }

    public static function valide(int $points, int $bonus, string $activityName, int $activityId, int $userScore, int $groupScore, int $nextStepId): self
    {
        return new self(true, null, null, $points, $bonus, $activityName, $activityId, $userScore, $groupScore, $nextStepId);
    }

    public static function refus(string $error, ?string $code, string $activityName, int $activityId, int $userScore, int $groupScore): self
    {
        return new self(false, $code, $error, 0, 0, $activityName, $activityId, $userScore, $groupScore);
    }
}
