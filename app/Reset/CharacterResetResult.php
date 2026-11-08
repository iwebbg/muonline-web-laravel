<?php

namespace App\Reset;

/**
 * Carries the exact audited before and after state of a completed reset.
 */
final readonly class CharacterResetResult
{
    /**
     * Creates an immutable reset result from normalized game state snapshots.
     *
     * @param  array<string, int>  $beforeSnapshot
     * @param  array<string, int>  $afterSnapshot
     */
    public function __construct(
        public string $characterName,
        public array $beforeSnapshot,
        public array $afterSnapshot,
        public int $zenCost,
        public bool $statsCleared,
        public InventoryPolicy $inventoryPolicy,
    ) {}

    /**
     * Converts the completed result to the stable account-panel response shape.
     *
     * @return array<string, mixed>
     */
    public function toClientArray(): array
    {
        $snapshotKeys = ['level', 'resets', 'zen', 'points'];
        $resultingStats = array_diff_key(
            $this->afterSnapshot,
            array_flip($snapshotKeys),
        );

        return [
            'characterName' => $this->characterName,
            'currentLevel' => $this->beforeSnapshot['level'],
            'resultingLevel' => $this->afterSnapshot['level'],
            'currentResets' => $this->beforeSnapshot['resets'],
            'resultingResets' => $this->afterSnapshot['resets'],
            'currentZen' => $this->beforeSnapshot['zen'],
            'zenCost' => $this->zenCost,
            'resultingZen' => $this->afterSnapshot['zen'],
            'resultingPoints' => $this->afterSnapshot['points'],
            'resultingStats' => $resultingStats,
            'statsCleared' => $this->statsCleared,
            'inventoryPolicy' => $this->inventoryPolicy->value,
        ];
    }
}
