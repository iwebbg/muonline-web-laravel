<?php

namespace App\Game\Repositories;

use App\Game\Contracts\LuckyCoinBalanceRepository;
use Illuminate\Database\DatabaseManager;

/**
 * Reads the standard account-level Lucky Coin balance from MuEMU Season 6.
 */
final readonly class SqlServerMuEmuSeason6LuckyCoinBalanceRepository implements LuckyCoinBalanceRepository
{
    /**
     * Creates the adapter with the configured game database manager.
     */
    public function __construct(private DatabaseManager $database) {}

    /**
     * Returns zero for an account without an initialized Lucky Coin row.
     */
    public function balanceFor(string $accountId): ?int
    {
        $balance = $this->database
            ->connection('game')
            ->table('LuckyCoin')
            ->where('AccountID', $accountId)
            ->value('LuckyCoin');

        return max(0, (int) ($balance ?? 0));
    }
}
