<?php

namespace App\Service;

use App\Entity\User;

interface ParcoursService
{
    /**
     * génère le parcours ordonné depuis les top 3 sphères
     * @param array<int, array{sphereId: int, rating: int}> $preloadedRatings
     */
    public function generateForUser(User $user, array $preloadedRatings = []): void;

    /** @return array{steps: array<int, array{step: int, id: int, current: bool, done: bool}>, scannedIds: int[]} */
    public function getPathForMap(User $user): array;

    public function invalidatePath(User $user): void;
}
