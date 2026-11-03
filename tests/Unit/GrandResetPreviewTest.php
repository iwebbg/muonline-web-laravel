<?php

namespace Tests\Unit;

use App\Game\Data\AccountCharacter;
use App\Game\Versions\Version97d99i\Version97d99iProfile;
use App\GrandReset\Actions\PreviewCharacterGrandReset;
use App\GrandReset\GrandResetBlockReason;
use App\GrandReset\GrandResetPointsMode;
use App\GrandReset\GrandResetPreview;
use App\Reset\InventoryPolicy;
use App\Reset\PointsMode;
use App\Reset\ResetProfileSettings;
use PHPUnit\Framework\TestCase;

/**
 * Verifies deterministic DarksTeam grand-reset calculations without a database.
 */
final class GrandResetPreviewTest extends TestCase
{
    /**
     * Confirms the default DarksTeam policy returns the exact resulting state.
     */
    public function test_default_profile_returns_exact_grand_reset_outcome(): void
    {
        $preview = $this->preview();

        $this->assertTrue($preview->eligible);
        $this->assertSame(1, $preview->resultingLevel);
        $this->assertSame(20, $preview->resultingResets);
        $this->assertSame(5, $preview->resultingGrandResets);
        $this->assertSame(350, $preview->requiredLevel);
        $this->assertSame(100, $preview->requiredResets);
        $this->assertSame(999, $preview->maximumGrandResets);
        $this->assertSame(5_000_000, $preview->zenCost);
        $this->assertSame(195_000_000, $preview->resultingZen);
        $this->assertSame(1_700, $preview->resultingPoints);
        $this->assertSame(1_000, $preview->resultingStats->strength);
    }

    /**
     * Confirms mutable level, reset, Zen and maximum requirements are all reported.
     */
    public function test_mutable_requirements_block_ineligible_character(): void
    {
        $preview = $this->preview(
            character: $this->character(
                level: 300,
                resets: 99,
                grandResets: 999,
                zen: 1_000,
                online: true,
            ),
        );

        $this->assertFalse($preview->eligible);
        $this->assertContains(GrandResetBlockReason::CharacterOnline, $preview->blockReasons);
        $this->assertContains(GrandResetBlockReason::LevelTooLow, $preview->blockReasons);
        $this->assertContains(GrandResetBlockReason::InsufficientResets, $preview->blockReasons);
        $this->assertContains(GrandResetBlockReason::MaximumGrandResetsReached, $preview->blockReasons);
        $this->assertContains(GrandResetBlockReason::InsufficientZen, $preview->blockReasons);
        $this->assertSame(0, $preview->resultingResets);
    }

    /**
     * Confirms destructive inventory policies fail closed before Phase 5.
     */
    public function test_destructive_inventory_policy_is_rejected(): void
    {
        $preview = $this->preview(
            settings: $this->settings(
                inventoryPolicy: InventoryPolicy::ClearAll,
            ),
        );

        $this->assertFalse($preview->eligible);
        $this->assertContains(
            GrandResetBlockReason::InventoryPolicyUnsupported,
            $preview->blockReasons,
        );
    }

    /**
     * Confirms class defaults and cumulative points are calculated when configured.
     */
    public function test_clear_stats_and_cumulative_points_use_configured_policy(): void
    {
        $preview = $this->preview(
            settings: $this->settings(
                clearStats: true,
                pointsMode: GrandResetPointsMode::FixedCumulative,
            ),
        );

        $this->assertTrue($preview->eligible);
        $this->assertSame(2_500, $preview->resultingPoints);
        $this->assertSame(28, $preview->resultingStats->strength);
        $this->assertSame(20, $preview->resultingStats->agility);
        $this->assertSame(25, $preview->resultingStats->vitality);
        $this->assertSame(10, $preview->resultingStats->energy);
    }

    /**
     * Builds one preview from explicit portable fixtures.
     */
    private function preview(
        ?AccountCharacter $character = null,
        ?ResetProfileSettings $settings = null,
    ): GrandResetPreview {
        $action = new PreviewCharacterGrandReset(
            new Version97d99iProfile,
        );

        return $action->handle(
            character: $character ?? $this->character(),
            settings: $settings ?? ResetProfileSettings::defaults(),
        );
    }

    /**
     * Creates one portable character snapshot for grand-reset calculations.
     */
    private function character(
        int $level = 400,
        int $resets = 120,
        int $grandResets = 4,
        int $zen = 200_000_000,
        bool $online = false,
    ): AccountCharacter {
        return new AccountCharacter(
            name: 'OwnedHero',
            className: 'Blade Knight',
            classCode: 17,
            image: '/images/characters/blade-knight.png',
            level: $level,
            resets: $resets,
            grandResets: $grandResets,
            zen: $zen,
            points: 1_200,
            strength: 1_000,
            agility: 800,
            vitality: 700,
            energy: 200,
            map: 'Lorencia',
            online: $online,
        );
    }

    /**
     * Creates one typed profile with focused grand-reset overrides.
     */
    private function settings(
        bool $clearStats = false,
        GrandResetPointsMode $pointsMode = GrandResetPointsMode::AddFixed,
        InventoryPolicy $inventoryPolicy = InventoryPolicy::KeepAll,
    ): ResetProfileSettings {
        return new ResetProfileSettings(
            enabled: true,
            requiredLevel: 350,
            maximumResets: 999,
            maximumStat: 32_767,
            grandResetRequiredResets: 100,
            baseZenCost: 15_000_000,
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
            grandResetClearStats: $clearStats,
            grandResetPointsMode: $pointsMode,
            grandResetPoints: 500,
            grandResetInventoryPolicy: $inventoryPolicy,
            grandResetConsumeRequiredResets: true,
        );
    }
}
