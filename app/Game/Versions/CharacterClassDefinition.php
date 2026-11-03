<?php

namespace App\Game\Versions;

/**
 * Describes one playable class evolution in a version profile.
 */
final readonly class CharacterClassDefinition
{
    /**
     * Creates one stable class definition independent of legacy database names.
     */
    public function __construct(
        public int $code,
        public string $key,
        public string $abbreviation,
        public string $name,
        public string $image,
        public string $family,
        public int $evolutionTier,
        public ?int $previousClassCode = null,
    ) {}

    /**
     * Converts the definition to the public ranking-filter shape.
     *
     * @return array{value: string, label: string, name: string}
     */
    public function toFilterArray(): array
    {
        return [
            'value' => $this->key,
            'label' => $this->abbreviation,
            'name' => $this->name,
        ];
    }
}
