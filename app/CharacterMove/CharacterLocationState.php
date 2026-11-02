<?php

namespace App\CharacterMove;

/**
 * Carries the legacy location and resource state required by Move to Town.
 */
final readonly class CharacterLocationState
{
    /**
     * Creates an immutable ownership-scoped location snapshot.
     */
    public function __construct(
        public string $name,
        public int $map,
        public int $x,
        public int $y,
        public int $zen,
        public bool $online,
    ) {}
}
