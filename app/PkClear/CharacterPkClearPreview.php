<?php

namespace App\PkClear;

/**
 * Carries the calculated PK clear outcome without changing game data.
 */
final readonly class CharacterPkClearPreview
{
    /**
     * Creates an immutable preview with exact before and after values.
     *
     * @param  list<PkClearBlockReason>  $blockReasons
     */
    public function __construct(
        public string $characterName,
        public bool $eligible,
        public array $blockReasons,
        public int $requiredLevel,
        public int $currentLevel,
        public int $currentPkLevel,
        public int $resultingPkLevel,
        public int $currentPkCount,
        public int $resultingPkCount,
        public int $currentPkTime,
        public int $resultingPkTime,
        public int $zenCost,
        public int $currentZen,
        public int $resultingZen,
        public PkClearPriceMode $priceMode,
    ) {}

    /**
     * Converts the preview to the stable account-panel response shape.
     *
     * @return array<string, bool|int|string|list<string>>
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
            'currentPkLevel' => $this->currentPkLevel,
            'resultingPkLevel' => $this->resultingPkLevel,
            'currentPkCount' => $this->currentPkCount,
            'resultingPkCount' => $this->resultingPkCount,
            'currentPkTime' => $this->currentPkTime,
            'resultingPkTime' => $this->resultingPkTime,
            'zenCost' => $this->zenCost,
            'currentZen' => $this->currentZen,
            'resultingZen' => $this->resultingZen,
            'priceMode' => $this->priceMode->value,
        ];
    }
}
