<?php

namespace Tests\Unit;

use App\PkClear\Actions\PreviewCharacterPkClear;
use App\PkClear\CharacterPkState;
use App\PkClear\PkClearBlockReason;
use App\PkClear\PkClearPriceMode;
use App\Reset\ResetProfileSettings;
use PHPUnit\Framework\TestCase;

/**
 * Verifies deterministic DarksTeam-compatible PK clear calculations.
 */
final class CharacterPkClearPreviewTest extends TestCase
{
    /**
     * Confirms the default fixed-price profile clears complete PK state.
     */
    public function test_default_profile_returns_exact_pk_clear_outcome(): void
    {
        $preview = (new PreviewCharacterPkClear)->handle(
            character: $this->character(),
            settings: ResetProfileSettings::defaults(),
        );

        $this->assertTrue($preview->eligible);
        $this->assertSame(100_000, $preview->zenCost);
        $this->assertSame(9_900_000, $preview->resultingZen);
        $this->assertSame(3, $preview->resultingPkLevel);
        $this->assertSame(0, $preview->resultingPkCount);
        $this->assertSame(0, $preview->resultingPkTime);
    }

    /**
     * Confirms every DarksTeam price formula returns its exact charge.
     */
    public function test_price_modes_use_verified_darksteam_multipliers(): void
    {
        $this->assertSame(100_000, PkClearPriceMode::Fixed->zenCost(100_000, 6, 4));
        $this->assertSame(600_000, PkClearPriceMode::ByPkLevel->zenCost(100_000, 6, 4));
        $this->assertSame(400_000, PkClearPriceMode::ByPkCount->zenCost(100_000, 6, 4));
    }

    /**
     * Confirms online, level, murderer and Zen requirements fail closed.
     */
    public function test_ineligible_character_reports_all_current_failures(): void
    {
        $preview = (new PreviewCharacterPkClear)->handle(
            character: $this->character(
                level: 99,
                zen: 1,
                pkLevel: 3,
                online: true,
            ),
            settings: ResetProfileSettings::defaults(),
        );

        $this->assertFalse($preview->eligible);
        $this->assertContains(PkClearBlockReason::CharacterOnline, $preview->blockReasons);
        $this->assertContains(PkClearBlockReason::LevelTooLow, $preview->blockReasons);
        $this->assertContains(PkClearBlockReason::CharacterNotKiller, $preview->blockReasons);
        $this->assertContains(PkClearBlockReason::InsufficientZen, $preview->blockReasons);
    }

    /**
     * Creates one portable PK snapshot for focused preview tests.
     */
    private function character(
        int $level = 400,
        int $zen = 10_000_000,
        int $pkLevel = 6,
        int $pkCount = 4,
        int $pkTime = 3_600,
        bool $online = false,
    ): CharacterPkState {
        return new CharacterPkState(
            name: 'OwnedHero',
            level: $level,
            zen: $zen,
            pkLevel: $pkLevel,
            pkCount: $pkCount,
            pkTime: $pkTime,
            online: $online,
        );
    }
}
