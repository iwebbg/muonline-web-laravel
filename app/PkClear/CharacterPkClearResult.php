<?php

namespace App\PkClear;

/**
 * Carries the exact audited state of a completed character PK clear.
 */
final readonly class CharacterPkClearResult
{
    /**
     * Creates an immutable PK clear result from locked game state.
     */
    public function __construct(
        public string $characterName,
        public int $beforeZen,
        public int $afterZen,
        public int $beforePkLevel,
        public int $afterPkLevel,
        public int $beforePkCount,
        public int $afterPkCount,
        public int $beforePkTime,
        public int $afterPkTime,
    ) {}

    /**
     * Converts the result to the stable account-panel response shape.
     *
     * @return array<string, int|string>
     */
    public function toClientArray(): array
    {
        return [
            'characterName' => $this->characterName,
            'beforeZen' => $this->beforeZen,
            'afterZen' => $this->afterZen,
            'beforePkLevel' => $this->beforePkLevel,
            'afterPkLevel' => $this->afterPkLevel,
            'beforePkCount' => $this->beforePkCount,
            'afterPkCount' => $this->afterPkCount,
            'beforePkTime' => $this->beforePkTime,
            'afterPkTime' => $this->afterPkTime,
        ];
    }

    /**
     * Returns the normalized before snapshot stored in the audit record.
     *
     * @return array<string, int>
     */
    public function beforeSnapshot(): array
    {
        return [
            'zen' => $this->beforeZen,
            'pk_level' => $this->beforePkLevel,
            'pk_count' => $this->beforePkCount,
            'pk_time' => $this->beforePkTime,
        ];
    }

    /**
     * Returns the normalized after snapshot stored in the audit record.
     *
     * @return array<string, int>
     */
    public function afterSnapshot(): array
    {
        return [
            'zen' => $this->afterZen,
            'pk_level' => $this->afterPkLevel,
            'pk_count' => $this->afterPkCount,
            'pk_time' => $this->afterPkTime,
        ];
    }
}
