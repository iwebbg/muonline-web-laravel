<?php

namespace App\CharacterMove\Actions;

use App\CharacterMove\CharacterLocationState;
use App\CharacterMove\CharacterMoveBlockReason;
use App\CharacterMove\CharacterMovePreview;
use App\Game\Versions\Version97d99i\MapNameMapper;
use App\Reset\ResetProfileSettings;

/**
 * Calculates the configured Move to Town outcome without changing game data.
 */
final readonly class PreviewMoveToTown
{
    /**
     * Creates the preview calculator with its version-specific map labels.
     */
    public function __construct(
        private MapNameMapper $mapNameMapper,
    ) {}

    /**
     * Builds a server-authoritative preview from current character state.
     */
    public function handle(
        CharacterLocationState $character,
        ResetProfileSettings $settings,
    ): CharacterMovePreview {
        $blockReasons = [];

        if (! $settings->moveToTownEnabled) {
            $blockReasons[] = CharacterMoveBlockReason::ProfileDisabled;
        }

        if ($character->online) {
            $blockReasons[] = CharacterMoveBlockReason::CharacterOnline;
        }

        if ($character->zen < $settings->moveToTownZenCost) {
            $blockReasons[] = CharacterMoveBlockReason::InsufficientZen;
        }

        if (
            $character->map === $settings->moveToTownMap
            && $character->x === $settings->moveToTownX
            && $character->y === $settings->moveToTownY
        ) {
            $blockReasons[] = CharacterMoveBlockReason::AlreadyAtDestination;
        }

        return new CharacterMovePreview(
            characterName: $character->name,
            eligible: $blockReasons === [],
            blockReasons: $blockReasons,
            currentMap: $character->map,
            currentMapName: $this->mapNameMapper->nameFor($character->map),
            currentX: $character->x,
            currentY: $character->y,
            destinationMap: $settings->moveToTownMap,
            destinationMapName: $this->mapNameMapper->nameFor($settings->moveToTownMap),
            destinationX: $settings->moveToTownX,
            destinationY: $settings->moveToTownY,
            zenCost: $settings->moveToTownZenCost,
            currentZen: $character->zen,
            resultingZen: $character->zen - $settings->moveToTownZenCost,
        );
    }
}
