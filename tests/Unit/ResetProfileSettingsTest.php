<?php

namespace Tests\Unit;

use App\Reset\InventoryPolicy;
use App\Reset\PointsMode;
use App\Reset\ResetProfileSettings;
use PHPUnit\Framework\TestCase;

/**
 * Verifies deterministic reset policy calculations without database access.
 */
final class ResetProfileSettingsTest extends TestCase
{
    /**
     * Confirms that cumulative policies scale Zen and points by reset number.
     */
    public function test_cumulative_policy_scales_cost_and_points(): void
    {
        $settings = ResetProfileSettings::defaults();

        $this->assertSame(120_000_000, $settings->zenCostFor(8));
        $this->assertSame(4_000, $settings->pointsFor(8));
    }

    /**
     * Confirms that fixed policies return the configured values for every reset.
     */
    public function test_fixed_policy_keeps_cost_and_points_constant(): void
    {
        $settings = new ResetProfileSettings(
            enabled: true,
            requiredLevel: 350,
            maximumResets: 100,
            maximumStat: 32_000,
            grandResetRequiredResets: 100,
            baseZenCost: 25_000_000,
            multiplyCostByReset: false,
            clearStats: false,
            pointsMode: PointsMode::Fixed,
            pointsPerReset: 650,
            inventoryPolicy: InventoryPolicy::ClearBag,
        );

        $this->assertSame(25_000_000, $settings->zenCostFor(20));
        $this->assertSame(650, $settings->pointsFor(20));
    }

    /**
     * Confirms that invalid preview reset numbers cannot produce a zero multiplier.
     */
    public function test_calculations_use_first_reset_for_non_positive_preview_numbers(): void
    {
        $settings = ResetProfileSettings::defaults();

        $this->assertSame(15_000_000, $settings->zenCostFor(0));
        $this->assertSame(500, $settings->pointsFor(-4));
    }

    /**
     * Confirms DarksTeam grand-reset defaults consume resets and add fixed points.
     */
    public function test_grand_reset_defaults_match_darksteam_normal_profile(): void
    {
        $settings = ResetProfileSettings::defaults();

        $this->assertSame(5_000_000, $settings->grandResetZenCostFor(5));
        $this->assertSame(1_700, $settings->grandResetPointsAfter(1_200, 5));
        $this->assertSame(20, $settings->resetsAfterGrandReset(120));
        $this->assertSame(999, $settings->maximumGrandResets);
    }
}
