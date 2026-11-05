<?php

namespace App\Game\Versions;

/**
 * Describes one version-owned character-family filter used by shop offers.
 */
final readonly class ShopClassScopeDefinition
{
    /**
     * Creates one stable shop filter without coupling the version to presentation.
     */
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
