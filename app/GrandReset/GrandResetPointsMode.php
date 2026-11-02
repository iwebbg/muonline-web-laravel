<?php

namespace App\GrandReset;

/**
 * Defines how a grand reset calculates the resulting unallocated points.
 */
enum GrandResetPointsMode: string
{
    case AddFixed = 'add_fixed';
    case AddCumulative = 'add_cumulative';
    case FixedCumulative = 'fixed_cumulative';
    case Fixed = 'fixed';

    /**
     * Calculates resulting points using the selected DarksTeam-compatible formula.
     */
    public function pointsAfter(
        int $currentPoints,
        int $newGrandResetNumber,
        int $pointsPerGrandReset,
    ): int {
        return match ($this) {
            self::AddFixed => $currentPoints + $pointsPerGrandReset,
            self::AddCumulative => $currentPoints + ($pointsPerGrandReset * $newGrandResetNumber),
            self::FixedCumulative => $pointsPerGrandReset * $newGrandResetNumber,
            self::Fixed => $pointsPerGrandReset,
        };
    }
}
