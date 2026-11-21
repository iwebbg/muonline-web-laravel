<?php

namespace App\Game\Repositories;

use App\Game\Contracts\GameCurrencyBalanceRepository;
use App\Game\Data\GameCurrencyBalances;
use Illuminate\Database\DatabaseManager;

/**
 * Reads the five native currency types declared in the MUDevs S21 SQL schema.
 */
final readonly class SqlServerMuDevsSeason21CurrencyBalanceRepository implements GameCurrencyBalanceRepository
{
    /**
     * Creates the adapter against the isolated game database connection.
     */
    public function __construct(private DatabaseManager $database) {}

    /**
     * Returns nonnegative balances while treating uninitialized rows as zero.
     *
     * This display snapshot uses independent reads and must never authorize a
     * purchase or a transfer. Native currencies remain owned by the game server.
     */
    public function forAccount(string $accountId): GameCurrencyBalances
    {
        $connection = $this->database->connection('game');
        $cashShop = $connection
            ->table('CashShopData')
            ->where('AccountID', $accountId)
            ->first(['WCoinC', 'WCoinP', 'GoblinPoint']);
        $luckyCoins = $connection
            ->table('LuckyCoin')
            ->where('AccountID', $accountId)
            ->value('LuckyCoin');
        $characters = $connection
            ->table('Character')
            ->where('AccountID', $accountId)
            ->orderBy('Name')
            ->orderBy('CharacterIndex')
            ->get(['Name', 'RuudMoney']);
        $ruudByCharacter = [];

        foreach ($characters as $character) {
            $ruudByCharacter[(string) $character->Name] = max(0, (int) $character->RuudMoney);
        }

        return new GameCurrencyBalances(
            wCoinC: max(0, (int) ($cashShop?->WCoinC ?? 0)),
            wCoinP: max(0, (int) ($cashShop?->WCoinP ?? 0)),
            goblinPoints: max(0, (int) ($cashShop?->GoblinPoint ?? 0)),
            luckyCoins: max(0, (int) ($luckyCoins ?? 0)),
            ruudByCharacter: $ruudByCharacter,
        );
    }
}
