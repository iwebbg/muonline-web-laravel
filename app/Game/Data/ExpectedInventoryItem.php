<?php

namespace App\Game\Data;

/**
 * Carries one exact bag slot and its immutable bytes into a guarded extraction.
 */
final readonly class ExpectedInventoryItem
{
    /**
     * Creates one exact expected inventory item.
     */
    public function __construct(
        public int $slot,
        public string $binary,
    ) {}
}
