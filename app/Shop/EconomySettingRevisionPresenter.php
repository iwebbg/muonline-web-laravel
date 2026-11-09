<?php

namespace App\Shop;

use App\Models\EconomySettingRevision;
use Illuminate\Database\Eloquent\Collection;

/**
 * Converts immutable paid-service settings snapshots into administrator-facing changes.
 */
final class EconomySettingRevisionPresenter
{
    /** @var array<string, string> */
    private const FIELD_LABELS = [
        'itemGeneratorMarkupPercentage' => 'item_generator_markup',
        'characterRenameEnabled' => 'character_rename_availability',
        'characterRenamePriceCredits' => 'character_rename_price',
        'characterClassChangeEnabled' => 'character_class_change_availability',
        'characterClassChangePriceCredits' => 'character_class_change_price',
        'characterResetPurchaseEnabled' => 'character_reset_purchase_availability',
        'characterResetPriceCredits' => 'character_reset_purchase_price',
        'characterLevelUpPointsPurchaseEnabled' => 'character_level_up_points_purchase_availability',
        'characterLevelUpPointsPackageSize' => 'character_level_up_points_package_size',
        'characterLevelUpPointsPriceCredits' => 'character_level_up_points_purchase_price',
        'itemExchangeEnabled' => 'item_exchange_availability',
        'onlineTimeRewardsEnabled' => 'online_time_rewards_availability',
        'onlineMinutesPerUnit' => 'online_minutes_per_unit',
        'onlineCreditsPerUnit' => 'online_credits_per_unit',
        'onlineDailyCreditLimit' => 'online_daily_credit_limit',
    ];

    /**
     * Presents recent revisions with only the paid-service settings that changed.
     *
     * @param  Collection<int, EconomySettingRevision>  $revisions
     * @return list<array{revision: int, createdAt: string, changes: list<array{label: string, before: string, after: string}>}>
     */
    public function present(Collection $revisions): array
    {
        $presentedRevisions = [];

        foreach ($revisions as $revision) {
            $presentedRevisions[] = [
                'revision' => $revision->revision,
                'createdAt' => $revision->created_at?->diffForHumans() ?? '',
                'changes' => $this->changesFor($revision),
            ];
        }

        return $presentedRevisions;
    }

    /**
     * Extracts and labels changed paid-service values from one immutable snapshot pair.
     *
     * @return list<array{label: string, before: string, after: string}>
     */
    private function changesFor(EconomySettingRevision $revision): array
    {
        $beforeSnapshot = $revision->before_snapshot ?? [];
        $afterSnapshot = $revision->after_snapshot ?? [];
        $changes = [];

        foreach (self::FIELD_LABELS as $field => $labelKey) {
            $beforeValue = $beforeSnapshot[$field] ?? null;
            $afterValue = $afterSnapshot[$field] ?? null;

            if ($beforeValue === $afterValue) {
                continue;
            }

            $changes[] = [
                'label' => __('ui.admin.'.$labelKey),
                'before' => $this->formatValue($beforeValue),
                'after' => $this->formatValue($afterValue),
            ];
        }

        return $changes;
    }

    /**
     * Formats stored scalar values consistently with the other revision history panels.
     */
    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return __('ui.admin.revision_value_not_set');
        }

        if (is_bool($value)) {
            return $value
                ? __('ui.admin.revision_value_enabled')
                : __('ui.admin.revision_value_disabled');
        }

        if (is_int($value)) {
            return number_format($value);
        }

        return (string) $value;
    }
}
