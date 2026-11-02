<?php

namespace App\Stats;

/**
 * Carries one requested distribution of available character points.
 */
final readonly class StatAllocation
{
    /**
     * Creates an immutable allocation request.
     */
    public function __construct(
        public int $strength,
        public int $agility,
        public int $vitality,
        public int $energy,
    ) {}

    /**
     * Returns the complete number of points consumed by the allocation.
     */
    public function total(): int
    {
        return $this->strength
            + $this->agility
            + $this->vitality
            + $this->energy;
    }

    /**
     * Determines whether every requested amount is non-negative.
     */
    public function hasOnlyNonNegativeAmounts(): bool
    {
        return $this->strength >= 0
            && $this->agility >= 0
            && $this->vitality >= 0
            && $this->energy >= 0;
    }

    /**
     * Determines whether applying the allocation would exceed one stat limit.
     */
    public function exceedsMaximum(
        CharacterStats $currentStats,
        int $maximumStat,
    ): bool {
        return ($this->strength > 0 && $currentStats->strength + $this->strength > $maximumStat)
            || ($this->agility > 0 && $currentStats->agility + $this->agility > $maximumStat)
            || ($this->vitality > 0 && $currentStats->vitality + $this->vitality > $maximumStat)
            || ($this->energy > 0 && $currentStats->energy + $this->energy > $maximumStat);
    }

    /**
     * Converts the allocation to the stable request and audit shape.
     *
     * @return array{strength: int, agility: int, vitality: int, energy: int}
     */
    public function toArray(): array
    {
        return [
            'strength' => $this->strength,
            'agility' => $this->agility,
            'vitality' => $this->vitality,
            'energy' => $this->energy,
        ];
    }
}
