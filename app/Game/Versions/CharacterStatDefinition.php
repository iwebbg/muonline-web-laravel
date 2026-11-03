<?php

namespace App\Game\Versions;

/**
 * Describes one character attribute exposed by a version profile.
 */
final readonly class CharacterStatDefinition
{
    /**
     * Creates one attribute with localized labels and its legacy column mapping.
     */
    public function __construct(
        public string $key,
        public string $labelEn,
        public string $labelBg,
        public string $databaseColumn,
        public int $minimum,
        public int $maximum,
    ) {}

    /**
     * Returns the configured label with English fallback.
     */
    public function localizedLabel(string $locale): string
    {
        if ($locale === 'bg' && $this->labelBg !== '') {
            return $this->labelBg;
        }

        return $this->labelEn;
    }

    /**
     * Converts the definition to the account-panel shape.
     *
     * @return array{key: string, label: string, minimum: int, maximum: int}
     */
    public function toClientArray(string $locale): array
    {
        return [
            'key' => $this->key,
            'label' => $this->localizedLabel($locale),
            'minimum' => $this->minimum,
            'maximum' => $this->maximum,
        ];
    }
}
