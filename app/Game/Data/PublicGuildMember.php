<?php

namespace App\Game\Data;

/**
 * Carries one privacy-safe guild roster member across game versions.
 */
final readonly class PublicGuildMember
{
    /**
     * Creates one public roster row without exposing the owning account.
     */
    public function __construct(
        public string $name,
        public string $className,
        public int $level,
        public int $resets,
        public int $grandResets,
        public bool $online,
        public bool $master,
    ) {}
}
