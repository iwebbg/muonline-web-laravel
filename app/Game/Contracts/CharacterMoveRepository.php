<?php

namespace App\Game\Contracts;

use App\CharacterMove\CharacterLocationState;
use App\CharacterMove\CharacterMoveResult;
use App\Reset\ResetProfileSettings;

/**
 * Defines ownership-scoped reads and transactional moves for game characters.
 */
interface CharacterMoveRepository
{
    /**
     * Returns one ownership-scoped location snapshot without changing game data.
     */
    public function characterFor(
        string $accountId,
        string $characterName,
    ): ?CharacterLocationState;

    /**
     * Rechecks the character under locks and moves it to the configured town.
     */
    public function moveToTown(
        string $accountId,
        string $characterName,
        ResetProfileSettings $settings,
    ): CharacterMoveResult;
}
