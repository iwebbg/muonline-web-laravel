<?php

namespace App\Game\Versions;

/**
 * Declares optional game-schema features without probing legacy tables at runtime.
 */
final readonly class GameVersionCapabilities
{
    /**
     * Creates one immutable set of version-owned persistence capabilities.
     */
    public function __construct(
        public bool $grandResets,
        public bool $characterVip,
        public bool $billingRecord,
        public bool $itemGenerator = true,
        public bool $castleSiege = false,
        public bool $masterProgression = false,
        public bool $gens = false,
    ) {}
}
