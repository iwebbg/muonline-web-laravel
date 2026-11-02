<?php

namespace App\GrandReset\Actions;

use App\Game\Data\AccountCharacter;
use App\Game\Versions\Version97d99i\CharacterBaseStatsMapper;
use App\GrandReset\GrandResetBlockReason;
use App\GrandReset\GrandResetPreview;
use App\Reset\InventoryPolicy;
use App\Reset\ResetProfileSettings;
use App\Stats\CharacterStats;
use InvalidArgumentException;

/**
 * Calculates grand-reset eligibility and outcome without writing game data.
 */
final readonly class PreviewCharacterGrandReset
{
    /**
     * Creates the preview calculator with the active class-stat adapter.
     */
    public function __construct(
        private CharacterBaseStatsMapper $baseStatsMapper,
    ) {}

    /**
     * Builds a server-authoritative grand-reset preview from current game state.
     */
    public function handle(
        AccountCharacter $character,
        ResetProfileSettings $settings,
    ): GrandResetPreview {
        $resultingGrandResets = $character->grandResets + 1;
        $zenCost = $settings->grandResetZenCostFor($resultingGrandResets);
        $resultingPoints = $settings->grandResetPointsAfter(
            currentPoints: $character->points,
            grandResetNumber: $resultingGrandResets,
        );
        $currentStats = new CharacterStats(
            strength: $character->strength,
            agility: $character->agility,
            vitality: $character->vitality,
            energy: $character->energy,
        );
        $resultingStats = $currentStats;
        $blockReasons = [];

        if (! $settings->grandResetEnabled) {
            $blockReasons[] = GrandResetBlockReason::ProfileDisabled;
        }

        if ($character->online) {
            $blockReasons[] = GrandResetBlockReason::CharacterOnline;
        }

        if ($character->level < $settings->grandResetRequiredLevel) {
            $blockReasons[] = GrandResetBlockReason::LevelTooLow;
        }

        if ($character->resets < $settings->grandResetRequiredResets) {
            $blockReasons[] = GrandResetBlockReason::InsufficientResets;
        }

        if ($resultingGrandResets > $settings->maximumGrandResets) {
            $blockReasons[] = GrandResetBlockReason::MaximumGrandResetsReached;
        }

        if ($character->zen < $zenCost) {
            $blockReasons[] = GrandResetBlockReason::InsufficientZen;
        }

        if ($resultingPoints > 2_000_000_000) {
            $blockReasons[] = GrandResetBlockReason::PointLimitExceeded;
        }

        if ($settings->grandResetInventoryPolicy !== InventoryPolicy::KeepAll) {
            $blockReasons[] = GrandResetBlockReason::InventoryPolicyUnsupported;
        }

        if ($settings->grandResetClearStats) {
            try {
                $baseStats = $this->baseStatsMapper->forClass(
                    $character->classCode,
                );
                $resultingStats = new CharacterStats(
                    strength: $baseStats->strength,
                    agility: $baseStats->agility,
                    vitality: $baseStats->vitality,
                    energy: $baseStats->energy,
                );
            } catch (InvalidArgumentException) {
                $blockReasons[] = GrandResetBlockReason::CharacterClassUnsupported;
            }
        }

        return new GrandResetPreview(
            characterName: $character->name,
            eligible: $blockReasons === [],
            blockReasons: $blockReasons,
            requiredLevel: $settings->grandResetRequiredLevel,
            currentLevel: $character->level,
            resultingLevel: 1,
            requiredResets: $settings->grandResetRequiredResets,
            currentResets: $character->resets,
            resultingResets: $settings->resetsAfterGrandReset($character->resets),
            currentGrandResets: $character->grandResets,
            resultingGrandResets: $resultingGrandResets,
            currentZen: $character->zen,
            zenCost: $zenCost,
            resultingZen: $character->zen - $zenCost,
            currentPoints: $character->points,
            resultingPoints: $resultingPoints,
            currentStats: $currentStats,
            resultingStats: $resultingStats,
            clearStats: $settings->grandResetClearStats,
            inventoryPolicy: $settings->grandResetInventoryPolicy,
        );
    }
}
