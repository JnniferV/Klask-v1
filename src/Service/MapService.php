<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Sphere;

interface MapService
{
    public function getPreparedSpheresJson(): string;

    public function activityToArray(Activity $activity): array;

    public function invalidateCache(): void;

    public function savePosition(Sphere|Activity $entity, float $x, float $y, ?float $radius = null): void;
}
