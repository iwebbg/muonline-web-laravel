<?php

namespace App\Reset\Actions;

use App\Game\Data\AccountCharacter;
use App\Reset\ResetBlockReason;
use App\Reset\ResetPreview;
use App\Reset\ResetProfileSettings;

/**
 * Calculates reset eligibility and outcome without writing to either database.
 */
final class PreviewCharacterReset
{
    /**
     * Builds a server-authoritative reset preview from current character data.
     */
    public function handle(
        AccountCharacter $character,
        ResetProfileSettings $settings,
    ): ResetPreview {
        $resultingResets = $character->resets + 1;
        $zenCost = $settings->zenCostFor($resultingResets);
        $blockReasons = [];

        if (! $settings->enabled) {
            $blockReasons[] = ResetBlockReason::ProfileDisabled;
        }

        if ($character->online) {
            $blockReasons[] = ResetBlockReason::CharacterOnline;
        }

        if ($character->level < $settings->requiredLevel) {
            $blockReasons[] = ResetBlockReason::LevelTooLow;
        }

        if ($resultingResets > $settings->maximumResets) {
            $blockReasons[] = ResetBlockReason::MaximumResetsReached;
        }

        if ($character->zen < $zenCost) {
            $blockReasons[] = ResetBlockReason::InsufficientZen;
        }

        return new ResetPreview(
            characterName: $character->name,
            eligible: $blockReasons === [],
            blockReasons: $blockReasons,
            requiredLevel: $settings->requiredLevel,
            currentLevel: $character->level,
            resultingLevel: 1,
            currentResets: $character->resets,
            resultingResets: $resultingResets,
            currentZen: $character->zen,
            zenCost: $zenCost,
            resultingZen: $character->zen - $zenCost,
            resultingPoints: $settings->pointsFor($resultingResets),
            clearStats: $settings->clearStats,
            inventoryPolicy: $settings->inventoryPolicy,
        );
    }
}
