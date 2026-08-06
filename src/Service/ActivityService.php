<?php

namespace App\Service;

use App\Entity\Activity;

use App\Entity\ActivityCategory;
use App\Entity\Sphere;

interface ActivityService
{
    // token QR unique si l'activité n'en a pas encore
    public function initQrCode(Activity $activity): void;

    // crée et initialise le QR code, persiste et flush new activité depuis la carte
    public function createFromMap(Sphere $sphere, ActivityCategory $category, string $name, ?string $description, float $x, float $y): Activity;
}
