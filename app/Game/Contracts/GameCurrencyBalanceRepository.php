<?php

namespace App\Game\Contracts;

use App\Game\Data\GameCurrencyBalances;

/**
 * Reads native game balances privately for one account without allowing transfers.
 */
interface GameCurrencyBalanceRepository
{
    /**
     * Returns account coins and Ruud belonging only to the account's characters.
     */
    public function forAccount(string $accountId): GameCurrencyBalances;
}
