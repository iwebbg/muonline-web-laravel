<?php

namespace App\GrandReset;

use App\Reset\InventoryPolicy;
use App\Stats\CharacterStats;

/**
 * Carries the complete calculated outcome of a grand reset without mutation.
 */
final readonly class GrandResetPreview
{
    /**
     * Creates an immutable grand-reset preview with before and after values.
     *
     * @param  list<GrandResetBlockReason>  $blockReasons
     */
    public function __construct(
        public string $characterName,
        public bool $eligible,
        public array $blockReasons,
        public int $requiredLevel,
        public int $currentLevel,
        public int $resultingLevel,
        public int $requiredResets,
        public int $currentResets,
        public int $resultingResets,
        public int $currentGrandResets,
        public int $resultingGrandResets,
        public int $currentZen,
        public int $zenCost,
        public int $resultingZen,
        public int $currentPoints,
        public int $resultingPoints,
        public CharacterStats $currentStats,
        public CharacterStats $resultingStats,
        public bool $clearStats,
        public InventoryPolicy $inventoryPolicy,
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
            'currentLevel' => $this->currentLevel,
            'resultingLevel' => $this->resultingLevel,
            'requiredResets' => $this->requiredResets,
            'currentResets' => $this->currentResets,
            'resultingResets' => $this->resultingResets,
            'currentGrandResets' => $this->currentGrandResets,
            'resultingGrandResets' => $this->resultingGrandResets,
            'currentZen' => $this->currentZen,
            'zenCost' => $this->zenCost,
            'resultingZen' => $this->resultingZen,
            'currentPoints' => $this->currentPoints,
            'resultingPoints' => $this->resultingPoints,
            'currentStats' => $this->currentStats->toArray(),
            'resultingStats' => $this->resultingStats->toArray(),
            'clearStats' => $this->clearStats,
            'inventoryPolicy' => $this->inventoryPolicy->value,
        ];
    }
}
