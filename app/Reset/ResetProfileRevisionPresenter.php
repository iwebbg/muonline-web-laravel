<?php

namespace App\Reset;

use App\Models\ResetProfileRevision;
use Illuminate\Database\Eloquent\Collection;

/**
 * Converts immutable reset-profile snapshots into administrator-facing changes.
 */
final class ResetProfileRevisionPresenter
{
    /** @var array<string, string> */
    private const FIELD_LABELS = [
        'enabled' => 'enabled',
        'requiredLevel' => 'required_level',
        'maximumResets' => 'maximum_resets',
        'maximumStat' => 'maximum_stat',
        'baseZenCost' => 'base_zen_cost',
        'multiplyCostByReset' => 'scale_cost',
        'clearStats' => 'clear_stats',
        'pointsMode' => 'points_calculation',
        'pointsPerReset' => 'points_per_reset',
        'inventoryPolicy' => 'inventory_policy',
        'grandResetEnabled' => 'grand_reset_enabled',
        'grandResetRequiredLevel' => 'grand_reset_required_level',
        'grandResetRequiredResets' => 'grand_reset_required_resets',
        'maximumGrandResets' => 'maximum_grand_resets',
        'grandResetBaseZenCost' => 'grand_reset_zen_cost',
        'grandResetMultiplyCost' => 'grand_reset_scale_cost',
        'grandResetClearStats' => 'grand_reset_clear_stats',
        'grandResetPointsMode' => 'grand_reset_points_mode',
        'grandResetPoints' => 'grand_reset_points',
        'grandResetInventoryPolicy' => 'grand_reset_inventory_policy',
        'grandResetConsumeRequiredResets' => 'grand_reset_consume_resets',
        'statResetEnabled' => 'stat_reset_enabled',
        'statResetRequiredLevel' => 'stat_reset_required_level',
        'statResetRequiredResets' => 'stat_reset_required_resets',
        'statResetZenCost' => 'stat_reset_zen_cost',
        'pkClearEnabled' => 'pk_clear_enabled',
        'pkClearRequiredLevel' => 'pk_clear_required_level',
        'pkClearBaseZenCost' => 'pk_clear_base_zen_cost',
        'pkClearPriceMode' => 'pk_clear_price_mode',
        'moveToTownEnabled' => 'move_to_town_enabled',
        'moveToTownMap' => 'move_to_town_map',
        'moveToTownX' => 'move_to_town_x',
        'moveToTownY' => 'move_to_town_y',
        'moveToTownZenCost' => 'move_to_town_zen_cost',
    ];

    /** @var array<string, array<string, string>> */
    private const OPTION_LABELS = [
        'pointsMode' => [
            'cumulative' => 'points_cumulative',
            'fixed' => 'points_fixed',
        ],
        'inventoryPolicy' => [
            'keep_all' => 'keep_all',
            'clear_bag' => 'clear_bag',
            'clear_all' => 'clear_all',
        ],
        'grandResetPointsMode' => [
            'add_fixed' => 'grand_points_add_fixed',
            'add_cumulative' => 'grand_points_add_cumulative',
            'fixed_cumulative' => 'grand_points_fixed_cumulative',
            'fixed' => 'grand_points_fixed',
        ],
        'grandResetInventoryPolicy' => [
            'keep_all' => 'keep_all',
            'clear_bag' => 'clear_bag',
            'clear_all' => 'clear_all',
        ],
        'pkClearPriceMode' => [
            'fixed' => 'pk_clear_price_fixed',
            'by_pk_level' => 'pk_clear_price_by_level',
            'by_pk_count' => 'pk_clear_price_by_count',
        ],
    ];

    /**
     * Presents recent revisions with only the values that changed in each save.
     *
     * @param  Collection<int, ResetProfileRevision>  $revisions
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
     * Extracts and labels the changed values from one immutable snapshot pair.
     *
     * @return list<array{label: string, before: string, after: string}>
     */
    private function changesFor(ResetProfileRevision $revision): array
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
                'before' => $this->formatValue($field, $beforeValue),
                'after' => $this->formatValue($field, $afterValue),
            ];
        }

        return $changes;
    }

    /**
     * Formats stored scalar values without exposing internal enum identifiers.
     */
    private function formatValue(string $field, mixed $value): string
    {
        if ($value === null) {
            return __('ui.admin.revision_value_not_set');
        }

        if (is_bool($value)) {
            return $value
                ? __('ui.admin.revision_value_enabled')
                : __('ui.admin.revision_value_disabled');
        }

        $fieldOptions = self::OPTION_LABELS[$field] ?? [];

        if (is_string($value) && array_key_exists($value, $fieldOptions)) {
            return __('ui.admin.'.$fieldOptions[$value]);
        }

        if (is_int($value)) {
            return number_format($value);
        }

        return (string) $value;
    }
}
