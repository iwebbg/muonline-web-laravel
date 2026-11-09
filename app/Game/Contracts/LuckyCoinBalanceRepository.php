<?php

namespace App\Game\Contracts;

/**
 * Defines account-scoped reads for the standard Lucky Coin balance.
 */
interface LuckyCoinBalanceRepository
{
    /**
     * Returns the balance or null when the active game profile has no adapter.
     */
    public function balanceFor(string $accountId): ?int;
}
