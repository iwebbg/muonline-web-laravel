<?php

namespace App\Game\Contracts;

use App\Reset\ResetProfileSettings;
use App\StatReset\CharacterStatResetResult;

/**
 * Defines the guarded game-database boundary for resetting character stats.
 */
interface CharacterStatResetRepository
{
    /**
     * Resets one owned offline character to verified class base stats.
     */
    public function resetStats(
        string $accountId,
        string $characterName,
        ResetProfileSettings $settings,
    ): CharacterStatResetResult;
}
