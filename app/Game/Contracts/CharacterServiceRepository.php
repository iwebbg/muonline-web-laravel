<?php

namespace App\Game\Contracts;

use App\CharacterServices\CharacterServiceResult;

/**
 * Mutates paid character identity fields behind an ownership-scoped boundary.
 */
interface CharacterServiceRepository
{
    /**
     * Renames one owned offline character and every verified name reference.
     */
    public function rename(string $accountId, string $currentName, string $newName): CharacterServiceResult;

    /**
     * Changes one owned offline character to an exact profile-supported class code.
     */
    public function changeClass(string $accountId, string $characterName, int $targetClassCode): CharacterServiceResult;
}
