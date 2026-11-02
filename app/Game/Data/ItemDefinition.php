<?php

namespace App\Game\Data;

/**
 * Describes one version-specific item shared by storage, market and shop features.
 */
final readonly class ItemDefinition
{
    /**
     * Creates immutable presentation metadata for one item group and index.
     */
    public function __construct(
        public int $group,
        public int $index,
        public string $name,
        public int $width,
        public int $height,
        public ?string $image,
    ) {}

    /**
     * Returns the stable group:index identifier used by every item feature.
     */
    public function code(): string
    {
        return $this->group.':'.$this->index;
    }
}
