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
        public bool $characterResetPurchaseEnabled,
        public int $characterResetPriceCredits,
        public bool $characterLevelUpPointsPurchaseEnabled,
        public int $characterLevelUpPointsPackageSize,
        public int $characterLevelUpPointsPriceCredits,
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
            characterResetPurchaseEnabled: false,
            characterResetPriceCredits: 100,
            characterLevelUpPointsPurchaseEnabled: false,
            characterLevelUpPointsPackageSize: 1_000,
            characterLevelUpPointsPriceCredits: 100,
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
            'characterResetPurchaseEnabled' => $this->characterResetPurchaseEnabled,
            'characterResetPriceCredits' => $this->characterResetPriceCredits,
            'characterLevelUpPointsPurchaseEnabled' => $this->characterLevelUpPointsPurchaseEnabled,
            'characterLevelUpPointsPackageSize' => $this->characterLevelUpPointsPackageSize,
            'characterLevelUpPointsPriceCredits' => $this->characterLevelUpPointsPriceCredits,
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
            'character_reset_purchase_enabled' => $this->characterResetPurchaseEnabled,
            'character_reset_price_credits' => $this->characterResetPriceCredits,
            'character_level_up_points_purchase_enabled' => $this->characterLevelUpPointsPurchaseEnabled,
            'character_level_up_points_package_size' => $this->characterLevelUpPointsPackageSize,
            'character_level_up_points_price_credits' => $this->characterLevelUpPointsPriceCredits,
        ];
    }

    /**
     * Calculates the exact Credits price for a supported reset package.
     */
    public function resetPurchasePrice(int $resetCount): int
    {
        return $this->characterResetPriceCredits * $resetCount;
    }

    /**
     * Calculates the exact points granted by a supported package multiplier.
     */
    public function levelUpPointsPurchaseAmount(int $packageCount): int
    {
        return $this->characterLevelUpPointsPackageSize * $packageCount;
    }

    /**
     * Calculates the exact Credits price for a supported points package multiplier.
     */
    public function levelUpPointsPurchasePrice(int $packageCount): int
    {
        return $this->characterLevelUpPointsPriceCredits * $packageCount;
    }
}
