<?php

namespace App\StatReset;

use App\Stats\CharacterStats;

/**
 * Carries the calculated stat-reset outcome without changing game data.
 */
final readonly class CharacterStatResetPreview
{
    /**
     * Creates an immutable preview with exact before and after values.
     *
     * @param  list<StatResetBlockReason>  $blockReasons
     */
    public function __construct(
        public string $characterName,
        public bool $eligible,
        public array $blockReasons,
        public int $requiredLevel,
        public int $requiredResets,
        public int $zenCost,
        public int $currentZen,
        public int $resultingZen,
        public int $currentPoints,
        public int $refundedPoints,
        public int $resultingPoints,
        public CharacterStats $currentStats,
        public CharacterStats $resultingStats,
    ) {}

    /**
     * Converts the preview to the stable account-panel response shape.
     *
     * @return array<string, bool|int|string|array<string, int>|list<string>>
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
            'requiredLevel' => $this->requiredLevel,
            'requiredResets' => $this->requiredResets,
            'zenCost' => $this->zenCost,
            'currentZen' => $this->currentZen,
            'resultingZen' => $this->resultingZen,
            'currentPoints' => $this->currentPoints,
            'refundedPoints' => $this->refundedPoints,
            'resultingPoints' => $this->resultingPoints,
            'currentStats' => $this->currentStats->toArray(),
            'resultingStats' => $this->resultingStats->toArray(),
        ];
    }
}
