<?php

namespace App\GrandReset;

use App\Stats\CharacterStats;

/**
 * Carries the exact audited before and after state of a completed grand reset.
 */
final readonly class CharacterGrandResetResult
{
    /**
     * Creates an immutable result from normalized locked game state.
     */
    public function __construct(
        public string $characterName,
        public int $beforeLevel,
        public int $afterLevel,
        public int $beforeResets,
        public int $afterResets,
        public int $beforeGrandResets,
        public int $afterGrandResets,
        public int $beforeZen,
        public int $afterZen,
        public int $beforePoints,
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
            'beforeLevel' => $this->beforeLevel,
            'afterLevel' => $this->afterLevel,
            'beforeResets' => $this->beforeResets,
            'afterResets' => $this->afterResets,
            'beforeGrandResets' => $this->beforeGrandResets,
            'afterGrandResets' => $this->afterGrandResets,
            'beforeZen' => $this->beforeZen,
            'afterZen' => $this->afterZen,
            'beforePoints' => $this->beforePoints,
            'afterPoints' => $this->afterPoints,
            'beforeStats' => $this->beforeStats->toArray(),
            'afterStats' => $this->afterStats->toArray(),
        ];
    }

    /**
     * Returns the normalized before snapshot persisted in the audit record.
     *
     * @return array<string, int|array<string, int>>
     */
    public function beforeSnapshot(): array
    {
        return [
            'level' => $this->beforeLevel,
            'resets' => $this->beforeResets,
            'grand_resets' => $this->beforeGrandResets,
            'zen' => $this->beforeZen,
            'points' => $this->beforePoints,
            'stats' => $this->beforeStats->toArray(),
        ];
    }

    /**
     * Returns the normalized after snapshot persisted in the audit record.
     *
     * @return array<string, int|array<string, int>>
     */
    public function afterSnapshot(): array
    {
        return [
            'level' => $this->afterLevel,
            'resets' => $this->afterResets,
            'grand_resets' => $this->afterGrandResets,
            'zen' => $this->afterZen,
            'points' => $this->afterPoints,
            'stats' => $this->afterStats->toArray(),
        ];
    }
}
