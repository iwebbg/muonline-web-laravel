<?php

namespace App\Shop;

/**
 * Carries version-independent pricing for generated items and character services.
 */
final readonly class EconomySettings
{
    /**
     * Creates one validated economy policy shared by every game profile.
     */
    public function __construct(
        public int $itemGeneratorMarkupPercentage,
        public bool $characterRenameEnabled,
        public int $characterRenamePriceCredits,
        public bool $characterClassChangeEnabled,
        public int $characterClassChangePriceCredits,
    ) {}

    /**
     * Returns conservative defaults that preserve existing generator prices.
     */
    public static function defaults(): self
    {
        return new self(
            itemGeneratorMarkupPercentage: 0,
            characterRenameEnabled: false,
            characterRenamePriceCredits: 0,
            characterClassChangeEnabled: false,
            characterClassChangePriceCredits: 0,
        );
    }

    /**
     * Applies the configured markup to one complete base price using integer rounding.
     */
    public function generatedItemPrice(int $basePriceCredits): int
    {
        $markupCredits = intdiv(
            ($basePriceCredits * $this->itemGeneratorMarkupPercentage) + 99,
            100,
        );

        return $basePriceCredits + $markupCredits;
    }

    /**
     * Calculates the markup component persisted in a generated-item price breakdown.
     */
    public function generatedItemMarkup(int $basePriceCredits): int
    {
        return $this->generatedItemPrice($basePriceCredits) - $basePriceCredits;
    }

    /**
     * Converts the policy to the shape consumed by administrator and player views.
     *
     * @return array<string, bool|int>
     */
    public function toClientArray(): array
    {
        return [
            'itemGeneratorMarkupPercentage' => $this->itemGeneratorMarkupPercentage,
            'characterRenameEnabled' => $this->characterRenameEnabled,
            'characterRenamePriceCredits' => $this->characterRenamePriceCredits,
            'characterClassChangeEnabled' => $this->characterClassChangeEnabled,
            'characterClassChangePriceCredits' => $this->characterClassChangePriceCredits,
        ];
    }

    /**
     * Converts the policy to explicit application database columns.
     *
     * @return array<string, bool|int>
     */
    public function toPersistenceArray(): array
    {
        return [
            'item_generator_markup_percentage' => $this->itemGeneratorMarkupPercentage,
            'character_rename_enabled' => $this->characterRenameEnabled,
            'character_rename_price_credits' => $this->characterRenamePriceCredits,
            'character_class_change_enabled' => $this->characterClassChangeEnabled,
            'character_class_change_price_credits' => $this->characterClassChangePriceCredits,
        ];
    }
}
