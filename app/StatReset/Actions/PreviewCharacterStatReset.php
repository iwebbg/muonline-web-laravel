<?php

namespace App\StatReset\Actions;

use App\Game\Data\AccountCharacter;
use App\Game\Versions\Version97d99i\CharacterBaseStatsMapper;
use App\Reset\ResetProfileSettings;
use App\StatReset\CharacterStatResetPreview;
use App\StatReset\StatResetBlockReason;
use App\Stats\CharacterStats;
use InvalidArgumentException;

/**
 * Calculates a safe class-aware stat reset without changing game data.
 */
final readonly class PreviewCharacterStatReset
{
    /**
     * Creates the preview calculator with its version-specific class mapper.
     */
    public function __construct(
        private CharacterBaseStatsMapper $baseStatsMapper,
    ) {}

    /**
     * Builds a server-authoritative stat-reset preview from current game state.
     */
    public function handle(
        AccountCharacter $character,
        ResetProfileSettings $settings,
    ): CharacterStatResetPreview {
        $currentStats = new CharacterStats(
            strength: $character->strength,
            agility: $character->agility,
            vitality: $character->vitality,
            energy: $character->energy,
        );
        $resultingStats = $currentStats;
        $refundedPoints = 0;
        $blockReasons = [];

        try {
            $baseStats = $this->baseStatsMapper->forClass($character->classCode);
            $resultingStats = new CharacterStats(
                strength: $baseStats->strength,
                agility: $baseStats->agility,
                vitality: $baseStats->vitality,
                energy: $baseStats->energy,
            );
            $hasInvalidStats = $character->strength < $baseStats->strength
                || $character->agility < $baseStats->agility
                || $character->vitality < $baseStats->vitality
                || $character->energy < $baseStats->energy;

            if ($hasInvalidStats) {
                $blockReasons[] = StatResetBlockReason::CharacterStatsInvalid;
            } else {
                $refundedPoints = ($character->strength - $baseStats->strength)
                    + ($character->agility - $baseStats->agility)
                    + ($character->vitality - $baseStats->vitality)
                    + ($character->energy - $baseStats->energy);
            }
        } catch (InvalidArgumentException) {
            $blockReasons[] = StatResetBlockReason::CharacterClassUnsupported;
        }

        $resultingPoints = $character->points + $refundedPoints;

        if (! $settings->statResetEnabled) {
            $blockReasons[] = StatResetBlockReason::ProfileDisabled;
        }

        if ($character->online) {
            $blockReasons[] = StatResetBlockReason::CharacterOnline;
        }

        if ($character->level < $settings->statResetRequiredLevel) {
            $blockReasons[] = StatResetBlockReason::LevelTooLow;
        }

        if ($character->resets < $settings->statResetRequiredResets) {
            $blockReasons[] = StatResetBlockReason::InsufficientResets;
        }

        if ($character->zen < $settings->statResetZenCost) {
            $blockReasons[] = StatResetBlockReason::InsufficientZen;
        }

        if ($refundedPoints === 0) {
            $blockReasons[] = StatResetBlockReason::NoAllocatedStats;
        }

        if ($resultingPoints > 2_000_000_000) {
            $blockReasons[] = StatResetBlockReason::PointLimitExceeded;
        }

        return new CharacterStatResetPreview(
            characterName: $character->name,
            eligible: $blockReasons === [],
            blockReasons: $blockReasons,
            requiredLevel: $settings->statResetRequiredLevel,
            requiredResets: $settings->statResetRequiredResets,
            zenCost: $settings->statResetZenCost,
            currentZen: $character->zen,
            resultingZen: $character->zen - $settings->statResetZenCost,
            currentPoints: $character->points,
            refundedPoints: $refundedPoints,
            resultingPoints: $resultingPoints,
            currentStats: $currentStats,
            resultingStats: $resultingStats,
        );
    }
}
