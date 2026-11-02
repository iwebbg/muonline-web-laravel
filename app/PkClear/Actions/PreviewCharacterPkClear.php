<?php

namespace App\PkClear\Actions;

use App\PkClear\CharacterPkClearPreview;
use App\PkClear\CharacterPkState;
use App\PkClear\PkClearBlockReason;
use App\Reset\ResetProfileSettings;

/**
 * Calculates DarksTeam-compatible PK clear eligibility without a write.
 */
final readonly class PreviewCharacterPkClear
{
    /**
     * Builds a server-authoritative PK clear preview from current game state.
     */
    public function handle(
        CharacterPkState $character,
        ResetProfileSettings $settings,
    ): CharacterPkClearPreview {
        $zenCost = $settings->pkClearZenCostFor(
            pkLevel: $character->pkLevel,
            pkCount: $character->pkCount,
        );
        $blockReasons = [];

        if (! $settings->pkClearEnabled) {
            $blockReasons[] = PkClearBlockReason::ProfileDisabled;
        }

        if ($character->online) {
            $blockReasons[] = PkClearBlockReason::CharacterOnline;
        }

        if ($character->level < $settings->pkClearRequiredLevel) {
            $blockReasons[] = PkClearBlockReason::LevelTooLow;
        }

        if ($character->pkLevel <= 3) {
            $blockReasons[] = PkClearBlockReason::CharacterNotKiller;
        }

        if ($zenCost > 2_000_000_000) {
            $blockReasons[] = PkClearBlockReason::PriceLimitExceeded;
        }

        if ($character->zen < $zenCost) {
            $blockReasons[] = PkClearBlockReason::InsufficientZen;
        }

        return new CharacterPkClearPreview(
            characterName: $character->name,
            eligible: $blockReasons === [],
            blockReasons: $blockReasons,
            requiredLevel: $settings->pkClearRequiredLevel,
            currentLevel: $character->level,
            currentPkLevel: $character->pkLevel,
            resultingPkLevel: 3,
            currentPkCount: $character->pkCount,
            resultingPkCount: 0,
            currentPkTime: $character->pkTime,
            resultingPkTime: 0,
            zenCost: $zenCost,
            currentZen: $character->zen,
            resultingZen: $character->zen - $zenCost,
            priceMode: $settings->pkClearPriceMode,
        );
    }
}
