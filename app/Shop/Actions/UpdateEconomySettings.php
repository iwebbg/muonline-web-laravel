<?php

namespace App\Shop\Actions;

use App\Models\Administrator;
use App\Models\EconomySetting;
use App\Models\EconomySettingRevision;
use App\Shop\EconomySettings;
use Illuminate\Database\DatabaseManager;

/**
 * Persists paid-service settings and one complete immutable audit revision.
 */
final readonly class UpdateEconomySettings
{
    /**
     * Creates the settings action with the application database transaction boundary.
     */
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * Saves the current settings and their before/after audit snapshots atomically.
     */
    public function handle(EconomySettings $settings, Administrator $administrator): EconomySetting
    {
        return $this->database->transaction(function () use ($settings, $administrator): EconomySetting {
            $model = EconomySetting::query()->lockForUpdate()->first();
            $beforeSettings = $model instanceof EconomySetting
                ? new EconomySettings(
                    itemGeneratorMarkupPercentage: $model->item_generator_markup_percentage,
                    characterRenameEnabled: $model->character_rename_enabled,
                    characterRenamePriceCredits: $model->character_rename_price_credits,
                    characterClassChangeEnabled: $model->character_class_change_enabled,
                    characterClassChangePriceCredits: $model->character_class_change_price_credits,
                    characterResetPurchaseEnabled: $model->character_reset_purchase_enabled,
                    characterResetPriceCredits: $model->character_reset_price_credits,
                    characterLevelUpPointsPurchaseEnabled: $model->character_level_up_points_purchase_enabled,
                    characterLevelUpPointsPackageSize: $model->character_level_up_points_package_size,
                    characterLevelUpPointsPriceCredits: $model->character_level_up_points_price_credits,
                    itemExchangeEnabled: $model->item_exchange_enabled,
                    onlineTimeRewardsEnabled: $model->online_time_rewards_enabled,
                    onlineMinutesPerUnit: $model->online_minutes_per_unit,
                    onlineCreditsPerUnit: $model->online_credits_per_unit,
                    onlineDailyCreditLimit: $model->online_daily_credit_limit,
                )
                : EconomySettings::defaults();
            $revision = ($model?->revision ?? 0) + 1;
            $attributes = [
                ...$settings->toPersistenceArray(),
                'revision' => $revision,
                'updated_by_administrator_id' => $administrator->getKey(),
            ];

            if (! $model instanceof EconomySetting) {
                $model = EconomySetting::query()->create($attributes);
            } else {
                $model->update($attributes);
            }

            EconomySettingRevision::query()->create([
                'economy_setting_id' => $model->getKey(),
                'revision' => $revision,
                'before_snapshot' => $beforeSettings->toClientArray(),
                'after_snapshot' => $settings->toClientArray(),
                'changed_by_administrator_id' => $administrator->getKey(),
            ]);

            return $model->refresh();
        });
    }
}
