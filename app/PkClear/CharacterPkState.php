<?php

namespace App\PkClear;

/**
 * Carries the PK-specific state required by preview and write boundaries.
 */
final readonly class CharacterPkState
{
    /**
     * Creates an immutable ownership-scoped character PK snapshot.
     */
    public function __construct(
        public string $name,
        public int $level,
        public int $zen,
        public int $pkLevel,
        public int $pkCount,
        public int $pkTime,
        public bool $online,
    ) {}
}
