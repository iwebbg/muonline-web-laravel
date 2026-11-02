<?php

namespace Tests\Unit;

use App\CharacterMove\Actions\PreviewMoveToTown;
use App\CharacterMove\CharacterLocationState;
use App\Game\Versions\Version97d99i\MapNameMapper;
use App\Reset\InventoryPolicy;
use App\Reset\PointsMode;
use App\Reset\ResetProfileSettings;
use PHPUnit\Framework\TestCase;

/**
 * Verifies deterministic Move to Town eligibility and exact outcomes.
 */
final class CharacterMovePreviewTest extends TestCase
{
    /**
     * Confirms the DarksTeam Lorencia default produces an exact free move.
     */
    public function test_default_profile_moves_an_offline_character_to_lorencia(): void
    {
        $preview = $this->preview()->handle(
            new CharacterLocationState('TestHero', 8, 200, 60, 10_000_000, false),
            ResetProfileSettings::defaults(),
        );

        $this->assertTrue($preview->eligible);
        $this->assertSame('Tarkan', $preview->currentMapName);
        $this->assertSame('Lorencia', $preview->destinationMapName);
        $this->assertSame(0, $preview->destinationMap);
        $this->assertSame(141, $preview->destinationX);
        $this->assertSame(127, $preview->destinationY);
        $this->assertSame(10_000_000, $preview->resultingZen);
    }

    /**
     * Confirms online, insufficient-Zen and duplicate-location failures are explicit.
     */
    public function test_preview_returns_every_applicable_block_reason(): void
    {
        $settings = new ResetProfileSettings(
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
            moveToTownEnabled: false,
            moveToTownMap: 0,
            moveToTownX: 141,
            moveToTownY: 127,
            moveToTownZenCost: 2_000,
        );
        $preview = $this->preview()->handle(
            new CharacterLocationState('TestHero', 0, 141, 127, 1_000, true),
            $settings,
        );

        $this->assertFalse($preview->eligible);
        $this->assertSame(
            ['profile_disabled', 'character_online', 'insufficient_zen', 'already_at_destination'],
            $preview->toClientArray()['blockReasons'],
        );
    }

    /**
     * Creates the pure preview calculator with the 97d+99i map mapper.
     */
    private function preview(): PreviewMoveToTown
    {
        return new PreviewMoveToTown(new MapNameMapper);
    }
}
