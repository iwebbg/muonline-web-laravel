<?php

namespace App\Shop;

use App\Game\Versions\Contracts\GameVersionProfile;
use App\Models\EconomySetting;
use App\Models\EconomySettingRevision;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads the active paid-service settings with safe pre-configuration defaults.
 */
final class EconomySettingReader
{
    /**
     * Restricts configured paid services to the selected adapter's reviewed endpoints.
     */
    public function __construct(private readonly GameVersionProfile $profile) {}

    /**
     * Returns the persisted settings or defaults before the first administrator save.
     */
    public function current(): EconomySettings
    {
        $settings = EconomySetting::query()->first();

        if ($settings === null) {
            return $this->supportedSettings(EconomySettings::defaults());
        }

        return $this->supportedSettings(new EconomySettings(
            itemGeneratorMarkupPercentage: $settings->item_generator_markup_percentage,
            characterRenameEnabled: $settings->character_rename_enabled,
            characterRenamePriceCredits: $settings->character_rename_price_credits,
            characterClassChangeEnabled: $settings->character_class_change_enabled,
            characterClassChangePriceCredits: $settings->character_class_change_price_credits,
            characterResetPurchaseEnabled: $settings->character_reset_purchase_enabled,
            characterResetPriceCredits: $settings->character_reset_price_credits,
            characterLevelUpPointsPurchaseEnabled: $settings->character_level_up_points_purchase_enabled,
            characterLevelUpPointsPackageSize: $settings->character_level_up_points_package_size,
            characterLevelUpPointsPriceCredits: $settings->character_level_up_points_price_credits,
            itemExchangeEnabled: $settings->item_exchange_enabled,
            onlineTimeRewardsEnabled: $settings->online_time_rewards_enabled,
            onlineMinutesPerUnit: $settings->online_minutes_per_unit,
            onlineCreditsPerUnit: $settings->online_credits_per_unit,
            onlineDailyCreditLimit: $settings->online_daily_credit_limit,
        ));
    }

    /**
     * Keeps unavailable paid tools hidden even when older persisted settings enable them.
     */
    private function supportedSettings(EconomySettings $settings): EconomySettings
    {
        $capabilities = $this->profile->capabilities();
        $values = get_object_vars($settings);
        $routes = [
            'characterRenameEnabled' => 'account.character-rename',
            'characterClassChangeEnabled' => 'account.character-class-change',
            'characterResetPurchaseEnabled' => 'account.character-reset-purchases.store',
            'characterLevelUpPointsPurchaseEnabled' => 'account.character-level-up-points-purchases.store',
            'itemExchangeEnabled' => 'account.credit-item-exchanges.store',
            'onlineTimeRewardsEnabled' => 'account.online-time-claims.store',
        ];

        foreach ($routes as $setting => $route) {
            $values[$setting] = $values[$setting] && $capabilities->allowsRoute($route);
        }

        return new EconomySettings(...$values);
    }

    /**
     * Returns the active persisted revision number or zero before first save.
     */
    public function revision(): int
    {
        return (int) (EconomySetting::query()->value('revision') ?? 0);
    }

    /**
     * Returns the newest immutable paid-service settings revisions.
     *
     * @return Collection<int, EconomySettingRevision>
     */
    public function recentRevisions(int $limit = 8): Collection
    {
        return EconomySettingRevision::query()
            ->latest('revision')
            ->limit(max(1, min($limit, 25)))
            ->get();
    }
}
