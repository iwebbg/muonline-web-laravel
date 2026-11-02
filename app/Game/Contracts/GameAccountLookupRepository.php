<?php

namespace App\Game\Contracts;

/**
 * Defines a password-free existence check against the active game account profile.
 */
interface GameAccountLookupRepository
{
    /**
     * Determines whether one exact legacy account identifier exists.
     */
    public function exists(string $accountId): bool;
}
