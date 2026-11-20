<?php

namespace App\Game\Versions\MuDevsSeason21;

/**
 * Preserves native S21 identity, geometry and source flags without legacy option guesses.
 */
final readonly class ItemMetadataMuDevsSeason21
{
    /**
     * Creates a source-backed item; native flags remain uninterpreted until verified.
     *
     * @param  array<string, string>  $sourceAttributes  Original Item.xml attributes.
     */
    public function __construct(
        public int $group,
        public int $index,
        public string $name,
        public int $width,
        public int $height,
        public int $equipmentSlot,
        public array $sourceAttributes,
        public ?string $image = null,
    ) {}

    /**
     * Returns the native section:index identity, including nine-bit item indices.
     */
    public function code(): string
    {
        return $this->group.':'.$this->index;
    }
}
