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
     * @param  array{level: int, resets: int, zen: int, points: int, strength: int, agility: int, vitality: int, energy: int}  $beforeSnapshot
     * @param  array{level: int, resets: int, zen: int, points: int, strength: int, agility: int, vitality: int, energy: int}  $afterSnapshot
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
     * @return array{characterName: string, currentLevel: int, resultingLevel: int, currentResets: int, resultingResets: int, currentZen: int, zenCost: int, resultingZen: int, resultingPoints: int, resultingStats: array{strength: int, agility: int, vitality: int, energy: int}, statsCleared: bool, inventoryPolicy: string}
     */
    public function toClientArray(): array
    {
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
            'resultingStats' => [
                'strength' => $this->afterSnapshot['strength'],
                'agility' => $this->afterSnapshot['agility'],
                'vitality' => $this->afterSnapshot['vitality'],
                'energy' => $this->afterSnapshot['energy'],
            ],
            'statsCleared' => $this->statsCleared,
            'inventoryPolicy' => $this->inventoryPolicy->value,
        ];
    }
}
