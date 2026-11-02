<?php

namespace App\Reset;

use App\GrandReset\GrandResetPointsMode;
use App\PkClear\PkClearPriceMode;

/**
 * Carries one validated reset policy without leaking unstructured setting arrays.
 */
final readonly class ResetProfileSettings
{
    /**
     * Creates an immutable reset policy used by previews and persistence.
     */
    public function __construct(
        public bool $enabled,
        public int $requiredLevel,
        public int $maximumResets,
        public int $maximumStat,
        public int $grandResetRequiredResets,
        public int $baseZenCost,
        public bool $multiplyCostByReset,
        public bool $clearStats,
        public PointsMode $pointsMode,
        public int $pointsPerReset,
        public InventoryPolicy $inventoryPolicy,
        public bool $grandResetEnabled = true,
        public int $grandResetRequiredLevel = 350,
        public int $maximumGrandResets = 999,
        public int $grandResetBaseZenCost = 5_000_000,
        public bool $grandResetMultiplyCost = false,
        public bool $grandResetClearStats = false,
        public GrandResetPointsMode $grandResetPointsMode = GrandResetPointsMode::AddFixed,
        public int $grandResetPoints = 500,
        public InventoryPolicy $grandResetInventoryPolicy = InventoryPolicy::KeepAll,
        public bool $grandResetConsumeRequiredResets = true,
        public bool $statResetEnabled = true,
        public int $statResetRequiredLevel = 1,
        public int $statResetRequiredResets = 1,
        public int $statResetZenCost = 0,
        public bool $pkClearEnabled = true,
        public int $pkClearRequiredLevel = 100,
        public int $pkClearBaseZenCost = 100_000,
        public PkClearPriceMode $pkClearPriceMode = PkClearPriceMode::Fixed,
    ) {}

    /**
     * Returns the safe default policy before the first saved revision exists.
     */
    public static function defaults(): self
    {
        return new self(
            enabled: true,
            requiredLevel: 350,
            maximumResets: 999,
            maximumStat: 32_767,
            grandResetRequiredResets: 100,
            baseZenCost: 15000000,
            multiplyCostByReset: true,
            clearStats: true,
            pointsMode: PointsMode::Cumulative,
            pointsPerReset: 500,
            inventoryPolicy: InventoryPolicy::KeepAll,
            grandResetEnabled: true,
            grandResetRequiredLevel: 350,
            maximumGrandResets: 999,
            grandResetBaseZenCost: 5_000_000,
            grandResetMultiplyCost: false,
            grandResetClearStats: false,
            grandResetPointsMode: GrandResetPointsMode::AddFixed,
            grandResetPoints: 500,
            grandResetInventoryPolicy: InventoryPolicy::KeepAll,
            grandResetConsumeRequiredResets: true,
            statResetEnabled: true,
            statResetRequiredLevel: 1,
            statResetRequiredResets: 1,
            statResetZenCost: 0,
            pkClearEnabled: true,
            pkClearRequiredLevel: 100,
            pkClearBaseZenCost: 100_000,
            pkClearPriceMode: PkClearPriceMode::Fixed,
        );
    }

    /**
     * Calculates the DarksTeam-compatible Zen charge for clearing PK state.
     */
    public function pkClearZenCostFor(int $pkLevel, int $pkCount): int
    {
        return $this->pkClearPriceMode->zenCost(
            baseZenCost: $this->pkClearBaseZenCost,
            pkLevel: $pkLevel,
            pkCount: $pkCount,
        );
    }

    /**
     * Calculates the Zen charge for the next grand reset number.
     */
    public function grandResetZenCostFor(int $grandResetNumber): int
    {
        if (! $this->grandResetMultiplyCost) {
            return $this->grandResetBaseZenCost;
        }

        return $this->grandResetBaseZenCost * max(1, $grandResetNumber);
    }

    /**
     * Calculates unallocated points after one grand reset.
     */
    public function grandResetPointsAfter(
        int $currentPoints,
        int $grandResetNumber,
    ): int {
        return $this->grandResetPointsMode->pointsAfter(
            currentPoints: $currentPoints,
            newGrandResetNumber: $grandResetNumber,
            pointsPerGrandReset: $this->grandResetPoints,
        );
    }

    /**
     * Calculates remaining normal resets after one grand reset.
     */
    public function resetsAfterGrandReset(int $currentResets): int
    {
        if (! $this->grandResetConsumeRequiredResets) {
            return $currentResets;
        }

        return $currentResets - $this->grandResetRequiredResets;
    }

    /**
     * Calculates the Zen charge for a reset number without writing game data.
     */
    public function zenCostFor(int $resetNumber): int
    {
        if (! $this->multiplyCostByReset) {
            return $this->baseZenCost;
        }

        return $this->baseZenCost * max(1, $resetNumber);
    }

    /**
     * Calculates the level-up points granted for a reset number.
     */
    public function pointsFor(int $resetNumber): int
    {
        if ($this->pointsMode === PointsMode::Fixed) {
            return $this->pointsPerReset;
        }

        return $this->pointsPerReset * max(1, $resetNumber);
    }

    /**
     * Converts the policy to the camel-case shape consumed by the admin interface.
     *
     * @return array<string, bool|int|string>
     */
    public function toClientArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'requiredLevel' => $this->requiredLevel,
            'maximumResets' => $this->maximumResets,
            'maximumStat' => $this->maximumStat,
            'grandResetRequiredResets' => $this->grandResetRequiredResets,
            'baseZenCost' => $this->baseZenCost,
            'multiplyCostByReset' => $this->multiplyCostByReset,
            'clearStats' => $this->clearStats,
            'pointsMode' => $this->pointsMode->value,
            'pointsPerReset' => $this->pointsPerReset,
            'inventoryPolicy' => $this->inventoryPolicy->value,
            'grandResetEnabled' => $this->grandResetEnabled,
            'grandResetRequiredLevel' => $this->grandResetRequiredLevel,
            'maximumGrandResets' => $this->maximumGrandResets,
            'grandResetBaseZenCost' => $this->grandResetBaseZenCost,
            'grandResetMultiplyCost' => $this->grandResetMultiplyCost,
            'grandResetClearStats' => $this->grandResetClearStats,
            'grandResetPointsMode' => $this->grandResetPointsMode->value,
            'grandResetPoints' => $this->grandResetPoints,
            'grandResetInventoryPolicy' => $this->grandResetInventoryPolicy->value,
            'grandResetConsumeRequiredResets' => $this->grandResetConsumeRequiredResets,
            'statResetEnabled' => $this->statResetEnabled,
            'statResetRequiredLevel' => $this->statResetRequiredLevel,
            'statResetRequiredResets' => $this->statResetRequiredResets,
            'statResetZenCost' => $this->statResetZenCost,
            'pkClearEnabled' => $this->pkClearEnabled,
            'pkClearRequiredLevel' => $this->pkClearRequiredLevel,
            'pkClearBaseZenCost' => $this->pkClearBaseZenCost,
            'pkClearPriceMode' => $this->pkClearPriceMode->value,
        ];
    }

    /**
     * Converts the policy to explicit database column values.
     *
     * @return array<string, bool|int|string>
     */
    public function toPersistenceArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'required_level' => $this->requiredLevel,
            'maximum_resets' => $this->maximumResets,
            'maximum_stat' => $this->maximumStat,
            'grand_reset_required_resets' => $this->grandResetRequiredResets,
            'base_zen_cost' => $this->baseZenCost,
            'multiply_cost_by_reset' => $this->multiplyCostByReset,
            'clear_stats' => $this->clearStats,
            'points_mode' => $this->pointsMode->value,
            'points_per_reset' => $this->pointsPerReset,
            'inventory_policy' => $this->inventoryPolicy->value,
            'grand_reset_enabled' => $this->grandResetEnabled,
            'grand_reset_required_level' => $this->grandResetRequiredLevel,
            'maximum_grand_resets' => $this->maximumGrandResets,
            'grand_reset_base_zen_cost' => $this->grandResetBaseZenCost,
            'grand_reset_multiply_cost' => $this->grandResetMultiplyCost,
            'grand_reset_clear_stats' => $this->grandResetClearStats,
            'grand_reset_points_mode' => $this->grandResetPointsMode->value,
            'grand_reset_points' => $this->grandResetPoints,
            'grand_reset_inventory_policy' => $this->grandResetInventoryPolicy->value,
            'grand_reset_consume_required_resets' => $this->grandResetConsumeRequiredResets,
            'stat_reset_enabled' => $this->statResetEnabled,
            'stat_reset_required_level' => $this->statResetRequiredLevel,
            'stat_reset_required_resets' => $this->statResetRequiredResets,
            'stat_reset_zen_cost' => $this->statResetZenCost,
            'pk_clear_enabled' => $this->pkClearEnabled,
            'pk_clear_required_level' => $this->pkClearRequiredLevel,
            'pk_clear_base_zen_cost' => $this->pkClearBaseZenCost,
            'pk_clear_price_mode' => $this->pkClearPriceMode->value,
        ];
    }
}
