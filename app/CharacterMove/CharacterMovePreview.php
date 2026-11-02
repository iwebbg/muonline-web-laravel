<?php

namespace App\CharacterMove;

/**
 * Carries the exact Move to Town outcome without changing game data.
 */
final readonly class CharacterMovePreview
{
    /**
     * Creates an immutable preview with exact source and destination values.
     *
     * @param  list<CharacterMoveBlockReason>  $blockReasons
     */
    public function __construct(
        public string $characterName,
        public bool $eligible,
        public array $blockReasons,
        public int $currentMap,
        public string $currentMapName,
        public int $currentX,
        public int $currentY,
        public int $destinationMap,
        public string $destinationMapName,
        public int $destinationX,
        public int $destinationY,
        public int $zenCost,
        public int $currentZen,
        public int $resultingZen,
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
            'currentMap' => $this->currentMap,
            'currentMapName' => $this->currentMapName,
            'currentX' => $this->currentX,
            'currentY' => $this->currentY,
            'destinationMap' => $this->destinationMap,
            'destinationMapName' => $this->destinationMapName,
            'destinationX' => $this->destinationX,
            'destinationY' => $this->destinationY,
            'zenCost' => $this->zenCost,
            'currentZen' => $this->currentZen,
            'resultingZen' => $this->resultingZen,
        ];
    }
}
