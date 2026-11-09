<?php

namespace App\Game\Data;

/**
 * Carries standard Master Level progression without exposing server-specific columns.
 */
final readonly class MasterProgression
{
    /**
     * Creates one immutable Master progression snapshot for an owned character.
     */
    public function __construct(
        public string $characterName,
        public int $level,
        public int $availablePoints,
        public int $experience,
    ) {}

    /**
     * Converts the snapshot to the stable shape consumed by the account workspace.
     *
     * @return array{level: int, availablePoints: int, experience: int}
     */
    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'availablePoints' => $this->availablePoints,
            'experience' => $this->experience,
        ];
    }
}
