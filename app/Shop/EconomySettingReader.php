<?php

namespace App\Shop;

use App\Models\EconomySetting;

/**
 * Reads the active paid-service settings with safe pre-configuration defaults.
 */
final class EconomySettingReader
{
    /**
     * Returns the persisted settings or defaults before the first administrator save.
     */
    public function current(): EconomySettings
    {
        $settings = EconomySetting::query()->first();

        if ($settings === null) {
            return EconomySettings::defaults();
        }

        return new EconomySettings(
            itemGeneratorMarkupPercentage: $settings->item_generator_markup_percentage,
            characterRenameEnabled: $settings->character_rename_enabled,
            characterRenamePriceCredits: $settings->character_rename_price_credits,
            characterClassChangeEnabled: $settings->character_class_change_enabled,
            characterClassChangePriceCredits: $settings->character_class_change_price_credits,
        );
    }

    /**
     * Returns the active persisted revision number or zero before first save.
     */
    public function revision(): int
    {
        return (int) (EconomySetting::query()->value('revision') ?? 0);
    }
}
