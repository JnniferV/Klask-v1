<?php

namespace App\Service;

use App\Entity\User;

interface UserService
{
    /**
     * @param array<string, int> $zoneRatings Zone name : rating (1–6)
     * @return array<int, array{sphereId: int, rating: int}>
     */
    public function saveRatings(User $user, array $zoneRatings): array;

    /** @return array{top: int[], bottom: int[]} Id des 3 meilleures et 3 moins bonnes sphères */
    public function getTopAndBottomSphereIds(User $user): array;

    public function hasCompletedQuestionnaire(User $user): bool;
}
