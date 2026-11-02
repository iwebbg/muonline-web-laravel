<?php

namespace App\Game\Contracts;

use App\PkClear\CharacterPkClearResult;
use App\PkClear\CharacterPkState;
use App\Reset\ResetProfileSettings;

/**
 * Defines the read and guarded write boundary for character PK clear.
 */
interface CharacterPkClearRepository
{
    /**
     * Returns one ownership-scoped PK snapshot or null when it is not owned.
     */
    public function characterFor(
        string $accountId,
        string $characterName,
    ): ?CharacterPkState;

    /**
     * Clears one owned offline character's complete PK penalty state.
     */
    public function clearPk(
        string $accountId,
        string $characterName,
        ResetProfileSettings $settings,
    ): CharacterPkClearResult;
}
