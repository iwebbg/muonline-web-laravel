<?php

namespace App\Reset;

/**
 * Carries the complete calculated outcome of a reset without mutating game data.
 */
final readonly class ResetPreview
{
    /**
     * Creates an immutable preview with eligibility and before/after values.
     *
     * @param  list<ResetBlockReason>  $blockReasons
     */
    public function __construct(
        public string $characterName,
        public bool $eligible,
        public array $blockReasons,
        public int $requiredLevel,
        public int $currentLevel,
        public int $resultingLevel,
        public int $currentResets,
        public int $resultingResets,
        public int $currentZen,
        public int $zenCost,
        public int $resultingZen,
        public int $resultingPoints,
        public bool $clearStats,
        public InventoryPolicy $inventoryPolicy,
    ) {}

    /**
     * Converts the preview to the stable shape consumed by the account interface.
     *
     * @return array{characterName: string, eligible: bool, blockReasons: list<string>, requiredLevel: int, currentLevel: int, resultingLevel: int, currentResets: int, resultingResets: int, currentZen: int, zenCost: int, resultingZen: int, resultingPoints: int, clearStats: bool, inventoryPolicy: string}
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
            'currentResets' => $this->currentResets,
            'resultingResets' => $this->resultingResets,
            'currentZen' => $this->currentZen,
            'zenCost' => $this->zenCost,
            'resultingZen' => $this->resultingZen,
            'resultingPoints' => $this->resultingPoints,
            'clearStats' => $this->clearStats,
            'inventoryPolicy' => $this->inventoryPolicy->value,
        ];
    }
}
