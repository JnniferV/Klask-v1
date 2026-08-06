<?php

namespace App\Service;

use App\Entity\User;

interface ScanService
{
    /**
     * valide scan QR pour un élève et met à jour les scores
     * @return array{ok: bool, points: int, bonus: int, activityName: string, activityId: int, userScore: int, groupScore: int, error: ?string}
     */
    public function process(User $user, string $token): array;
}
