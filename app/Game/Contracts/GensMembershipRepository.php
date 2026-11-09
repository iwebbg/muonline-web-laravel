<?php

namespace App\Game\Contracts;

use App\Game\Data\GensMembership;

/**
 * Defines account-scoped reads for the standard Season 6 Gens system.
 */
interface GensMembershipRepository
{
    /**
     * Returns Gens memberships keyed by character name for one owned account.
     *
     * @return array<string, GensMembership>
     */
    public function forAccount(string $accountId): array;
}
