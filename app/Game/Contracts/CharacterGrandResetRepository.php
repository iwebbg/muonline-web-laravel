<?php

namespace App\Game\Contracts;

use App\GrandReset\CharacterGrandResetResult;
use App\Reset\ResetProfileSettings;

/**
 * Defines the transactional write boundary for one character grand reset.
 */
interface CharacterGrandResetRepository
{
    /**
     * Rechecks and grand-resets one owned character inside a game transaction.
     */
    public function grandReset(
        string $accountId,
        string $characterName,
        ResetProfileSettings $settings,
    ): CharacterGrandResetResult;
}
