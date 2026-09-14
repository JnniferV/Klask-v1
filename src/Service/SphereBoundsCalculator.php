<?php

namespace App\Service;

use App\Entity\Activity;

final class SphereBoundsCalculator
{
    private const PADDING = 6.0;

    /**
     * @param array<array{pointXActivity: float, pointYActivity: float}> $activities
     *
     * @return array{centerX: float, centerY: float, radius: float}|null
     */
    public static function fromActivities(array $activities): ?array
    {
        if (empty($activities)) {
            return null;
        }

        $xs = array_column($activities, 'pointXActivity');
        $ys = array_column($activities, 'pointYActivity');

        $centerX = round((min($xs) + max($xs)) / 2, 2);
        $centerY = round((min($ys) + max($ys)) / 2, 2);
        $radius = round(max(max($xs) - $centerX, max($ys) - $centerY) + self::PADDING, 1);

        return compact('centerX', 'centerY', 'radius');
    }

    /**
     * @param Activity[] $activities
     *
     * @return array{centerX: float, centerY: float, radius: float}|null
     */
    public static function fromStands(array $activities): ?array
    {
        return self::fromActivities(array_map(
            fn (Activity $a) => ['pointXActivity' => $a->getPointX() ?? 50.0, 'pointYActivity' => $a->getPointY() ?? 50.0],
            $activities,
        ));
    }

    /**
     * @param array{centerX: float, centerY: float, radius: float} $a
     * @param array{centerX: float, centerY: float, radius: float} $b
     */
    public static function overlap(array $a, array $b): bool
    {
        return hypot($a['centerX'] - $b['centerX'], $a['centerY'] - $b['centerY']) < $a['radius'] + $b['radius'];
    }
}
