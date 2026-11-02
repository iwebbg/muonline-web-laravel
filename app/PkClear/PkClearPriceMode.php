<?php

namespace App\PkClear;

/**
 * Selects the DarksTeam-compatible Zen formula for clearing PK state.
 */
enum PkClearPriceMode: string
{
    case Fixed = 'fixed';
    case ByPkLevel = 'by_pk_level';
    case ByPkCount = 'by_pk_count';

    /**
     * Calculates the price from the current PK level and count.
     */
    public function zenCost(int $baseZenCost, int $pkLevel, int $pkCount): int
    {
        return match ($this) {
            self::Fixed => $baseZenCost,
            self::ByPkLevel => $baseZenCost * max(1, $pkLevel),
            self::ByPkCount => $baseZenCost * max(1, $pkCount),
        };
    }
}
