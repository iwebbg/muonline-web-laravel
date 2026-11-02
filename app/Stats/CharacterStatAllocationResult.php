<?php

namespace App\Stats;

/**
 * Carries the exact audited before and after state of a completed allocation.
 */
final readonly class CharacterStatAllocationResult
{
    /**
     * Creates an immutable result from the locked game state.
     */
    public function __construct(
        public string $characterName,
        public CharacterStats $beforeStats,
        public CharacterStats $afterStats,
        public int $beforePoints,
        public int $afterPoints,
        public StatAllocation $allocation,
    ) {}

    /**
     * Converts the completed result to the stable account-panel response shape.
     *
     * @return array{characterName: string, beforeStats: array{strength: int, agility: int, vitality: int, energy: int}, afterStats: array{strength: int, agility: int, vitality: int, energy: int}, beforePoints: int, afterPoints: int, allocatedPoints: int}
     */
    public function toClientArray(): array
    {
        return [
            'characterName' => $this->characterName,
            'beforeStats' => $this->beforeStats->toArray(),
            'afterStats' => $this->afterStats->toArray(),
            'beforePoints' => $this->beforePoints,
            'afterPoints' => $this->afterPoints,
            'allocatedPoints' => $this->allocation->total(),
        ];
    }

    /**
     * Returns the normalized before snapshot persisted in the audit record.
     *
     * @return array{points: int, stats: array{strength: int, agility: int, vitality: int, energy: int}}
     */
    public function beforeSnapshot(): array
    {
        return [
            'points' => $this->beforePoints,
            'stats' => $this->beforeStats->toArray(),
        ];
    }

    /**
     * Returns the normalized after snapshot persisted in the audit record.
     *
     * @return array{points: int, stats: array{strength: int, agility: int, vitality: int, energy: int}}
     */
    public function afterSnapshot(): array
    {
        return [
            'points' => $this->afterPoints,
            'stats' => $this->afterStats->toArray(),
        ];
    }
}
