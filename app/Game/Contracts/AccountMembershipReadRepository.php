<?php

namespace App\Game\Contracts;

use App\Game\Data\AccountMembership;

/**
 * Reads account-wide game membership independently of website character VIP purchases.
 */
interface AccountMembershipReadRepository
{
    /**
     * Returns the exact account's membership, or null when that account no longer exists.
     */
    public function forAccount(string $accountId): ?AccountMembership;
}
