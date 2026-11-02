<?php

namespace Tests\Unit;

use App\Game\Data\AccountCharacter;
use App\Game\Versions\Version97d99i\CharacterStatLimitMapper;
use App\Reset\InventoryPolicy;
use App\Reset\PointsMode;
use App\Reset\ResetProfileSettings;
use App\Stats\Actions\PreviewCharacterStatAllocation;
use App\Stats\StatAllocation;
use App\Stats\StatAllocationBlockReason;
use App\Stats\StatAllocationPreview;
use PHPUnit\Framework\TestCase;

/**
 * Verifies deterministic point allocation rules without database access.
 */
final class StatAllocationPreviewTest extends TestCase
{
    /**
     * Confirms a valid offline allocation returns exact resulting stats and points.
     */
    public function test_valid_allocation_returns_exact_outcome(): void
    {
        $preview = $this->preview(
            allocation: new StatAllocation(
                strength: 100,
                agility: 200,
                vitality: 50,
                energy: 0,
            ),
        );

        $this->assertTrue($preview->eligible);
        $this->assertSame(350, $preview->allocatedPoints);
        $this->assertSame(850, $preview->remainingPoints);
        $this->assertSame(1_100, $preview->resultingStats->strength);
        $this->assertSame(1_000, $preview->resultingStats->agility);
        $this->assertSame(750, $preview->resultingStats->vitality);
        $this->assertSame(200, $preview->resultingStats->energy);
    }

    /**
     * Confirms the administrator maximum stat overrides the higher version ceiling.
     */
    public function test_administrator_maximum_stat_blocks_overflow(): void
    {
        $preview = $this->preview(
            allocation: new StatAllocation(
                strength: 51,
                agility: 0,
                vitality: 0,
                energy: 0,
            ),
            maximumStat: 1_050,
        );

        $this->assertFalse($preview->eligible);
        $this->assertSame(1_050, $preview->maximumStat);
        $this->assertContains(
            StatAllocationBlockReason::StatLimitExceeded,
            $preview->blockReasons,
        );
    }

    /**
     * Confirms mutable game state can block an otherwise valid distribution.
     */
    public function test_online_and_insufficient_points_are_both_reported(): void
    {
        $preview = $this->preview(
            allocation: new StatAllocation(
                strength: 1_201,
                agility: 0,
                vitality: 0,
                energy: 0,
            ),
            online: true,
        );

        $this->assertFalse($preview->eligible);
        $this->assertContains(
            StatAllocationBlockReason::CharacterOnline,
            $preview->blockReasons,
        );
        $this->assertContains(
            StatAllocationBlockReason::InsufficientPoints,
            $preview->blockReasons,
        );
    }

    /**
     * Confirms empty and negative domain allocations fail closed.
     */
    public function test_empty_and_negative_allocations_are_rejected(): void
    {
        $emptyPreview = $this->preview(
            new StatAllocation(0, 0, 0, 0),
        );
        $negativePreview = $this->preview(
            new StatAllocation(-1, 0, 0, 0),
        );

        $this->assertContains(
            StatAllocationBlockReason::NoPointsAllocated,
            $emptyPreview->blockReasons,
        );
        $this->assertContains(
            StatAllocationBlockReason::InvalidAllocation,
            $negativePreview->blockReasons,
        );
    }

    /**
     * Confirms unknown legacy class codes fail closed before any mutation.
     */
    public function test_unknown_character_class_is_rejected(): void
    {
        $preview = $this->preview(
            allocation: new StatAllocation(10, 0, 0, 0),
            classCode: 64,
        );

        $this->assertFalse($preview->eligible);
        $this->assertContains(
            StatAllocationBlockReason::CharacterClassUnsupported,
            $preview->blockReasons,
        );
    }

    /**
     * Builds one preview from explicit portable fixtures.
     */
    private function preview(
        StatAllocation $allocation,
        int $maximumStat = 32_767,
        bool $online = false,
        int $classCode = 17,
    ): StatAllocationPreview {
        $action = new PreviewCharacterStatAllocation(
            new CharacterStatLimitMapper,
        );

        return $action->handle(
            character: $this->character(
                online: $online,
                classCode: $classCode,
            ),
            allocation: $allocation,
            settings: $this->settings($maximumStat),
        );
    }

    /**
     * Creates one portable Blade Knight snapshot for allocation calculations.
     */
    private function character(
        bool $online,
        int $classCode,
    ): AccountCharacter {
        return new AccountCharacter(
            name: 'OwnedHero',
            className: 'Blade Knight',
            classCode: $classCode,
            image: '/images/characters/blade-knight.png',
            level: 400,
            resets: 4,
            grandResets: 1,
            zen: 200_000_000,
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
     * Creates one typed profile with an explicit administrator stat limit.
     */
    private function settings(int $maximumStat): ResetProfileSettings
    {
        return new ResetProfileSettings(
            enabled: true,
            requiredLevel: 350,
            maximumResets: 100,
            maximumStat: $maximumStat,
            grandResetRequiredResets: 100,
            baseZenCost: 15_000_000,
            multiplyCostByReset: true,
            clearStats: true,
            pointsMode: PointsMode::Cumulative,
            pointsPerReset: 500,
            inventoryPolicy: InventoryPolicy::KeepAll,
        );
    }
}
