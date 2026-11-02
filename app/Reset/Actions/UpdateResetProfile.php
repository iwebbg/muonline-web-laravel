<?php

namespace App\Reset\Actions;

use App\Models\Administrator;
use App\Models\ResetProfile;
use App\Models\ResetProfileRevision;
use App\Reset\ResetProfileSettings;
use Illuminate\Database\DatabaseManager;

/**
 * Persists one reset policy revision and its complete audit snapshots atomically.
 */
final readonly class UpdateResetProfile
{
    /**
     * Creates the action with Laravel's application database manager.
     */
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * Saves the policy and audit record in one application database transaction.
     */
    public function handle(ResetProfileSettings $settings, Administrator $administrator): ResetProfile
    {
        return $this->database->transaction(function () use ($settings, $administrator): ResetProfile {
            $profile = ResetProfile::query()->lockForUpdate()->first();
            $beforeSettings = $profile === null
                ? ResetProfileSettings::defaults()
                : new ResetProfileSettings(
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
            $revision = ($profile?->revision ?? 0) + 1;
            $attributes = [
                ...$settings->toPersistenceArray(),
                'revision' => $revision,
                'updated_by_administrator_id' => $administrator->id,
            ];

            if ($profile === null) {
                $profile = ResetProfile::query()->create($attributes);
            } else {
                $profile->update($attributes);
            }

            ResetProfileRevision::query()->create([
                'reset_profile_id' => $profile->id,
                'revision' => $revision,
                'before_snapshot' => $beforeSettings->toClientArray(),
                'after_snapshot' => $settings->toClientArray(),
                'changed_by_administrator_id' => $administrator->id,
            ]);

            return $profile->refresh();
        });
    }
}
