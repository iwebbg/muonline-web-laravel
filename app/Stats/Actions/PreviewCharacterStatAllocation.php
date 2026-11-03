<?php

namespace App\Stats\Actions;

use App\Game\Data\AccountCharacter;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Reset\ResetProfileSettings;
use App\Stats\StatAllocation;
use App\Stats\StatAllocationBlockReason;
use App\Stats\StatAllocationPreview;
use InvalidArgumentException;

/**
 * Calculates stat allocation eligibility and outcome without writing game data.
 */
final readonly class PreviewCharacterStatAllocation
{
    /**
     * Creates the preview calculator with the active version stat-limit adapter.
     */
    public function __construct(
        private GameVersionProfile $versionProfile,
    ) {}

    /**
     * Builds a server-authoritative allocation preview from current character data.
     */
    public function handle(
        AccountCharacter $character,
        StatAllocation $allocation,
        ResetProfileSettings $settings,
    ): StatAllocationPreview {
        $currentStats = $character->stats();
        $resultingStats = $currentStats->add($allocation);
        $allocatedPoints = $allocation->total();
        $blockReasons = [];
        $maximumStat = 0;
        $maximumStats = [];
        $supportedStatKeys = [];

        try {
            $statDefinitions = $this->versionProfile->statsForClass($character->classCode);

            foreach ($statDefinitions as $statDefinition) {
                $supportedStatKeys[] = $statDefinition->key;
                $maximumStats[$statDefinition->key] = min(
                    $statDefinition->maximum,
                    $settings->maximumStat,
                );
            }

            $maximumStat = $maximumStats === []
                ? 0
                : min($maximumStats);
        } catch (InvalidArgumentException) {
            $blockReasons[] = StatAllocationBlockReason::CharacterClassUnsupported;
        }

        if ($character->online) {
            $blockReasons[] = StatAllocationBlockReason::CharacterOnline;
        }

        if (
            ! $allocation->hasOnlyNonNegativeAmounts()
            || $allocation->allocatesOutside($supportedStatKeys)
        ) {
            $blockReasons[] = StatAllocationBlockReason::InvalidAllocation;
        }

        if ($allocatedPoints === 0) {
            $blockReasons[] = StatAllocationBlockReason::NoPointsAllocated;
        }

        if ($allocatedPoints > $character->points) {
            $blockReasons[] = StatAllocationBlockReason::InsufficientPoints;
        }

        if (
            $maximumStat > 0
            && $allocation->exceedsMaximum($currentStats, $maximumStats)
        ) {
            $blockReasons[] = StatAllocationBlockReason::StatLimitExceeded;
        }

        return new StatAllocationPreview(
            characterName: $character->name,
            eligible: $blockReasons === [],
            blockReasons: $blockReasons,
            currentStats: $currentStats,
            resultingStats: $resultingStats,
            currentPoints: $character->points,
            allocatedPoints: $allocatedPoints,
            remainingPoints: $character->points - $allocatedPoints,
            maximumStat: $maximumStat,
        );
    }
}
