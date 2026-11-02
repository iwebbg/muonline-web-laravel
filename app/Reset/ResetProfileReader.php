<?php

namespace App\Reset;

use App\Models\ResetProfile;
use App\Models\ResetProfileRevision;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads the current reset policy and its immutable revision history.
 */
final class ResetProfileReader
{
    /**
     * Returns the persisted reset policy or safe typed defaults.
     */
    public function current(): ResetProfileSettings
    {
        $profile = ResetProfile::query()->first();

        if ($profile === null) {
            return ResetProfileSettings::defaults();
        }

        return new ResetProfileSettings(
            enabled: $profile->enabled,
            requiredLevel: $profile->required_level,
            maximumResets: $profile->maximum_resets,
            maximumStat: $profile->maximum_stat,
            grandResetRequiredResets: $profile->grand_reset_required_resets,
            baseZenCost: $profile->base_zen_cost,
            multiplyCostByReset: $profile->multiply_cost_by_reset,
            clearStats: $profile->clear_stats,
            pointsMode: $profile->points_mode,
            pointsPerReset: $profile->points_per_reset,
            inventoryPolicy: $profile->inventory_policy,
            grandResetEnabled: $profile->grand_reset_enabled,
            grandResetRequiredLevel: $profile->grand_reset_required_level,
            maximumGrandResets: $profile->maximum_grand_resets,
            grandResetBaseZenCost: $profile->grand_reset_base_zen_cost,
            grandResetMultiplyCost: $profile->grand_reset_multiply_cost,
            grandResetClearStats: $profile->grand_reset_clear_stats,
            grandResetPointsMode: $profile->grand_reset_points_mode,
            grandResetPoints: $profile->grand_reset_points,
            grandResetInventoryPolicy: $profile->grand_reset_inventory_policy,
            grandResetConsumeRequiredResets: $profile->grand_reset_consume_required_resets,
            statResetEnabled: $profile->stat_reset_enabled,
            statResetRequiredLevel: $profile->stat_reset_required_level,
            statResetRequiredResets: $profile->stat_reset_required_resets,
            statResetZenCost: $profile->stat_reset_zen_cost,
            pkClearEnabled: $profile->pk_clear_enabled,
            pkClearRequiredLevel: $profile->pk_clear_required_level,
            pkClearBaseZenCost: $profile->pk_clear_base_zen_cost,
            pkClearPriceMode: $profile->pk_clear_price_mode,
        );
    }

    /**
     * Returns the current persisted revision number or zero before the first save.
     */
    public function revision(): int
    {
        return (int) (ResetProfile::query()->value('revision') ?? 0);
    }

    /**
     * Returns the newest reset policy audit entries.
     *
     * @return Collection<int, ResetProfileRevision>
     */
    public function recentRevisions(int $limit = 8): Collection
    {
        return ResetProfileRevision::query()
            ->latest('revision')
            ->limit(max(1, min($limit, 25)))
            ->get();
    }
}
