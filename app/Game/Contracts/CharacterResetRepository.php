<?php

namespace App\Game\Contracts;

use App\Reset\CharacterResetResult;
use App\Reset\ResetProfileSettings;

/**
 * Defines the transactional write boundary for one character reset.
 */
interface CharacterResetRepository
{
    /**
     * Rechecks and resets one owned character inside a game-database transaction.
     */
    public function reset(
        string $accountId,
        string $characterName,
        ResetProfileSettings $settings,
    ): CharacterResetResult;
}
