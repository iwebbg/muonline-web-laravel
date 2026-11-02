<?php

namespace App\StatReset;

use App\Stats\CharacterStats;

/**
 * Carries the exact audited state of a completed character stat reset.
 */
final readonly class CharacterStatResetResult
{
    /**
     * Creates an immutable stat-reset result from locked game state.
     */
    public function __construct(
        public string $characterName,
        public int $beforeZen,
        public int $afterZen,
        public int $beforePoints,
        public int $refundedPoints,
        public int $afterPoints,
        public CharacterStats $beforeStats,
        public CharacterStats $afterStats,
    ) {}

    /**
     * Converts the result to the stable account-panel response shape.
     *
     * @return array<string, int|string|array<string, int>>
     */
    public function toClientArray(): array
    {
        return [
            'characterName' => $this->characterName,
            'beforeZen' => $this->beforeZen,
            'afterZen' => $this->afterZen,
            'beforePoints' => $this->beforePoints,
            'refundedPoints' => $this->refundedPoints,
            'afterPoints' => $this->afterPoints,
            'beforeStats' => $this->beforeStats->toArray(),
            'afterStats' => $this->afterStats->toArray(),
        ];
    }

    /**
     * Returns the normalized before snapshot stored in the audit record.
     *
     * @return array<string, int|array<string, int>>
     */
    public function beforeSnapshot(): array
    {
        return [
            'zen' => $this->beforeZen,
            'points' => $this->beforePoints,
            'stats' => $this->beforeStats->toArray(),
        ];
    }

    /**
     * Returns the normalized after snapshot stored in the audit record.
     *
     * @return array<string, int|array<string, int>>
     */
    public function afterSnapshot(): array
    {
        return [
            'zen' => $this->afterZen,
            'points' => $this->afterPoints,
            'refunded_points' => $this->refundedPoints,
            'stats' => $this->afterStats->toArray(),
        ];
    }
}
