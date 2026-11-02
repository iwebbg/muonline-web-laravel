<?php

namespace Tests\Unit;

use App\Game\Data\AccountCharacter;
use App\Game\Versions\Version97d99i\CharacterBaseStatsMapper;
use App\Reset\ResetProfileSettings;
use App\StatReset\Actions\PreviewCharacterStatReset;
use App\StatReset\CharacterStatResetPreview;
use App\StatReset\StatResetBlockReason;
use PHPUnit\Framework\TestCase;

/**
 * Verifies class-aware stat refunds without a database.
 */
final class CharacterStatResetPreviewTest extends TestCase
{
    /**
     * Confirms only allocated points above class base stats are refunded.
     */
    public function test_allocated_points_are_refunded_without_refunding_base_stats(): void
    {
        $preview = $this->preview();

        $this->assertTrue($preview->eligible);
        $this->assertSame(2_617, $preview->refundedPoints);
        $this->assertSame(3_817, $preview->resultingPoints);
        $this->assertSame(200_000_000, $preview->resultingZen);
        $this->assertSame(28, $preview->resultingStats->strength);
        $this->assertSame(20, $preview->resultingStats->agility);
        $this->assertSame(25, $preview->resultingStats->vitality);
        $this->assertSame(10, $preview->resultingStats->energy);
    }

    /**
     * Confirms offline and progression requirements are reported together.
     */
    public function test_mutable_requirements_block_an_ineligible_character(): void
    {
        $preview = $this->preview(
            character: $this->character(
                level: 0,
                resets: 0,
                zen: 100,
                online: true,
            ),
        );

        $this->assertFalse($preview->eligible);
        $this->assertContains(StatResetBlockReason::CharacterOnline, $preview->blockReasons);
        $this->assertContains(StatResetBlockReason::LevelTooLow, $preview->blockReasons);
        $this->assertContains(StatResetBlockReason::InsufficientResets, $preview->blockReasons);
    }

    /**
     * Confirms base-only characters cannot repeatedly manufacture free points.
     */
    public function test_base_only_character_has_no_refundable_points(): void
    {
        $preview = $this->preview(
            character: $this->character(
                strength: 28,
                agility: 20,
                vitality: 25,
                energy: 10,
            ),
        );

        $this->assertFalse($preview->eligible);
        $this->assertSame(0, $preview->refundedPoints);
        $this->assertContains(StatResetBlockReason::NoAllocatedStats, $preview->blockReasons);
    }

    /**
     * Confirms corrupt below-base stats fail closed instead of minting points.
     */
    public function test_below_base_stats_are_rejected(): void
    {
        $preview = $this->preview(
            character: $this->character(strength: 1),
        );

        $this->assertFalse($preview->eligible);
        $this->assertSame(0, $preview->refundedPoints);
        $this->assertContains(StatResetBlockReason::CharacterStatsInvalid, $preview->blockReasons);
    }

    /**
     * Builds one preview from explicit portable fixtures.
     */
    private function preview(?AccountCharacter $character = null): CharacterStatResetPreview
    {
        $action = new PreviewCharacterStatReset(
            new CharacterBaseStatsMapper,
        );

        return $action->handle(
            character: $character ?? $this->character(),
            settings: ResetProfileSettings::defaults(),
        );
    }

    /**
     * Creates one portable Blade Knight snapshot for stat-reset calculations.
     */
    private function character(
        int $level = 400,
        int $resets = 20,
        int $zen = 200_000_000,
        bool $online = false,
        int $strength = 1_000,
        int $agility = 800,
        int $vitality = 700,
        int $energy = 200,
    ): AccountCharacter {
        return new AccountCharacter(
            name: 'OwnedHero',
            className: 'Blade Knight',
            classCode: 17,
            image: '',
            level: $level,
            resets: $resets,
            grandResets: 4,
            zen: $zen,
            points: 1_200,
            strength: $strength,
            agility: $agility,
            vitality: $vitality,
            energy: $energy,
            map: 'Lorencia',
            online: $online,
        );
    }
}
