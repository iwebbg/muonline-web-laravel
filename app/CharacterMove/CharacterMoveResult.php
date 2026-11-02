<?php

namespace App\CharacterMove;

/**
 * Carries the exact audited state of a completed character move.
 */
final readonly class CharacterMoveResult
{
    /**
     * Creates an immutable result from the locked game state.
     */
    public function __construct(
        public string $characterName,
        public int $beforeMap,
        public int $beforeX,
        public int $beforeY,
        public int $beforeZen,
        public int $afterMap,
        public int $afterX,
        public int $afterY,
        public int $afterZen,
        public string $afterMapName,
    ) {}

    /**
     * Converts the completed move to the stable account-panel response shape.
     *
     * @return array<string, int|string>
     */
    public function toClientArray(): array
    {
        return [
            'characterName' => $this->characterName,
            'beforeMap' => $this->beforeMap,
            'beforeX' => $this->beforeX,
            'beforeY' => $this->beforeY,
            'beforeZen' => $this->beforeZen,
            'afterMap' => $this->afterMap,
            'afterX' => $this->afterX,
            'afterY' => $this->afterY,
            'afterZen' => $this->afterZen,
            'afterMapName' => $this->afterMapName,
        ];
    }

    /**
     * Returns the normalized source snapshot stored in the audit record.
     *
     * @return array<string, int>
     */
    public function beforeSnapshot(): array
    {
        return [
            'map' => $this->beforeMap,
            'x' => $this->beforeX,
            'y' => $this->beforeY,
            'zen' => $this->beforeZen,
        ];
    }

    /**
     * Returns the normalized destination snapshot stored in the audit record.
     *
     * @return array<string, int>
     */
    public function afterSnapshot(): array
    {
        return [
            'map' => $this->afterMap,
            'x' => $this->afterX,
            'y' => $this->afterY,
            'zen' => $this->afterZen,
        ];
    }
}
