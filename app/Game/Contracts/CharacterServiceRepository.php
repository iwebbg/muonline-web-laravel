<?php

namespace App\Game\Contracts;

use App\CharacterServices\CharacterServiceResult;
use App\CharacterServices\PurchasedResetPolicy;

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

    /**
     * Applies a paid reset package without charging Zen or granting reset rewards.
     */
    public function purchaseResets(
        string $accountId,
        string $characterName,
        int $resetCount,
        PurchasedResetPolicy $policy,
    ): CharacterServiceResult;

    /**
     * Adds purchased unallocated points to one owned offline character.
     */
    public function purchaseLevelUpPoints(
        string $accountId,
        string $characterName,
        int $pointCount,
    ): CharacterServiceResult;
}
