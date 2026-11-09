<?php

namespace App\Game\Repositories;

use App\Game\Contracts\LuckyCoinBalanceRepository;

/**
 * Disables Lucky Coin reads for profiles without a verified standard adapter.
 */
final class UnavailableLuckyCoinBalanceRepository implements LuckyCoinBalanceRepository
{
    /**
     * Reports that Lucky Coin is unavailable for the selected profile.
     */
    public function balanceFor(string $accountId): ?int
    {
        return null;
    }
}
