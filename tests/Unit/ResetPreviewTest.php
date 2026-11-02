<?php

namespace Tests\Unit;

use App\Game\Data\AccountCharacter;
use App\Reset\Actions\PreviewCharacterReset;
use App\Reset\InventoryPolicy;
use App\Reset\PointsMode;
use App\Reset\ResetBlockReason;
use App\Reset\ResetProfileSettings;
use PHPUnit\Framework\TestCase;

/**
 * Verifies deterministic reset eligibility and outcome calculations.
 */
final class ResetPreviewTest extends TestCase
{
    /**
     * Confirms that an eligible character receives the complete next-reset outcome.
     */
    public function test_eligible_character_receives_calculated_reset_outcome(): void
    {
        $preview = (new PreviewCharacterReset)->handle(
            character: $this->character(),
            settings: ResetProfileSettings::defaults(),
        );

        $this->assertTrue($preview->eligible);
        $this->assertSame([], $preview->blockReasons);
        $this->assertSame(5, $preview->resultingResets);
        $this->assertSame(75_000_000, $preview->zenCost);
        $this->assertSame(125_000_000, $preview->resultingZen);
        $this->assertSame(2_500, $preview->resultingPoints);
        $this->assertSame(InventoryPolicy::KeepAll, $preview->inventoryPolicy);
    }

    /**
     * Confirms that every failed server rule is reported in one preview response.
     */
    public function test_ineligible_character_receives_every_block_reason(): void
    {
        $character = $this->character(
            level: 100,
            resets: 20,
            zen: 1,
            online: true,
        );
        $settings = new ResetProfileSettings(
            enabled: false,
            requiredLevel: 350,
            maximumResets: 20,
            maximumStat: 32_767,
            grandResetRequiredResets: 100,
            baseZenCost: 15_000_000,
            multiplyCostByReset: true,
            clearStats: true,
            pointsMode: PointsMode::Cumulative,
            pointsPerReset: 500,
            inventoryPolicy: InventoryPolicy::KeepAll,
        );

        $preview = (new PreviewCharacterReset)->handle($character, $settings);

        $this->assertFalse($preview->eligible);
        $this->assertSame([
            ResetBlockReason::ProfileDisabled,
            ResetBlockReason::CharacterOnline,
            ResetBlockReason::LevelTooLow,
            ResetBlockReason::MaximumResetsReached,
            ResetBlockReason::InsufficientZen,
        ], $preview->blockReasons);
    }

    /**
     * Confirms that fixed policies and destructive inventory modes remain visible.
     */
    public function test_preview_exposes_fixed_points_and_inventory_policy(): void
    {
        $settings = new ResetProfileSettings(
            enabled: true,
            requiredLevel: 350,
            maximumResets: 20,
            maximumStat: 32_767,
            grandResetRequiredResets: 100,
            baseZenCost: 10_000_000,
            multiplyCostByReset: false,
            clearStats: false,
            pointsMode: PointsMode::Fixed,
            pointsPerReset: 650,
            inventoryPolicy: InventoryPolicy::ClearAll,
        );

        $preview = (new PreviewCharacterReset)->handle($this->character(), $settings);

        $this->assertSame(10_000_000, $preview->zenCost);
        $this->assertSame(650, $preview->resultingPoints);
        $this->assertFalse($preview->clearStats);
        $this->assertSame(InventoryPolicy::ClearAll, $preview->inventoryPolicy);
    }

    /**
     * Creates one portable character snapshot for preview calculations.
     */
    private function character(
        int $level = 400,
        int $resets = 4,
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
            grandResets: 1,
            zen: $zen,
            points: 0,
            strength: 100,
            agility: 100,
            vitality: 100,
            energy: 100,
            map: 'Lorencia',
            online: $online,
        );
    }
}
