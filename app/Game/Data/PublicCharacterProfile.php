<?php

namespace App\Game\Data;

/**
 * Carries one privacy-safe public character profile across game versions.
 */
final readonly class PublicCharacterProfile
{
    /**
     * Creates a public character record without exposing account or currency data.
     */
    public function __construct(
        public string $name,
        public string $className,
        public string $image,
        public int $level,
        public int $resets,
        public int $grandResets,
        public string $map,
        public int $pkLevel,
        public ?string $guildName,
        public bool $online,
        public bool $vip,
    ) {}
}
