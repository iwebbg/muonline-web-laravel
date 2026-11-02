<?php

namespace App\Stats;

/**
 * Carries the complete server-authoritative preview of one stat allocation.
 */
final readonly class StatAllocationPreview
{
    /**
     * Creates an immutable preview with all eligibility and outcome values.
     *
     * @param  list<StatAllocationBlockReason>  $blockReasons
     */
    public function __construct(
        public string $characterName,
        public bool $eligible,
        public array $blockReasons,
        public CharacterStats $currentStats,
        public CharacterStats $resultingStats,
        public int $currentPoints,
        public int $allocatedPoints,
        public int $remainingPoints,
        public int $maximumStat,
    ) {}

    /**
     * Converts the preview to the stable account-panel response shape.
     *
     * @return array{characterName: string, eligible: bool, blockReasons: list<string>, currentStats: array{strength: int, agility: int, vitality: int, energy: int}, resultingStats: array{strength: int, agility: int, vitality: int, energy: int}, currentPoints: int, allocatedPoints: int, remainingPoints: int, maximumStat: int}
     */
    public function toClientArray(): array
    {
        $reasonCodes = [];

        foreach ($this->blockReasons as $blockReason) {
            $reasonCodes[] = $blockReason->value;
        }

        return [
            'characterName' => $this->characterName,
            'eligible' => $this->eligible,
            'blockReasons' => $reasonCodes,
            'currentStats' => $this->currentStats->toArray(),
            'resultingStats' => $this->resultingStats->toArray(),
            'currentPoints' => $this->currentPoints,
            'allocatedPoints' => $this->allocatedPoints,
            'remainingPoints' => $this->remainingPoints,
            'maximumStat' => $this->maximumStat,
        ];
    }
}
