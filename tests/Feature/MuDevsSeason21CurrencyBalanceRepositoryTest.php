<?php

namespace Tests\Feature;

use App\Game\Repositories\SqlServerMuDevsSeason21CurrencyBalanceRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Verifies private native currency reads using a portable S21 schema projection.
 */
final class MuDevsSeason21CurrencyBalanceRepositoryTest extends TestCase
{
    /**
     * Confirms account coins and character Ruud cannot leak across account owners.
     */
    public function test_reads_owned_currencies_separately_without_changing_game_balances(): void
    {
        $connection = $this->createGameSchema();
        $connection->table('CashShopData')->insert([
            ['AccountID' => 'owner', 'WCoinC' => 120, 'WCoinP' => 230, 'GoblinPoint' => 340],
            ['AccountID' => 'other', 'WCoinC' => 999, 'WCoinP' => 888, 'GoblinPoint' => 777],
        ]);
        $connection->table('LuckyCoin')->insert([
            ['AccountID' => 'owner', 'LuckyCoin' => 45],
            ['AccountID' => 'other', 'LuckyCoin' => 987],
        ]);
        $connection->table('Character')->insert([
            ['CharacterIndex' => 2, 'AccountID' => 'owner', 'Name' => 'Zeta', 'RuudMoney' => 6000],
            ['CharacterIndex' => 1, 'AccountID' => 'owner', 'Name' => 'Alpha', 'RuudMoney' => 5000],
            ['CharacterIndex' => 3, 'AccountID' => 'other', 'Name' => 'Hidden', 'RuudMoney' => 99999],
        ]);
        $before = $this->storedBalances($connection);

        $snapshot = $this->repository()->forAccount('owner');

        self::assertSame([
            'wCoinC' => 120,
            'wCoinP' => 230,
            'goblinPoints' => 340,
            'luckyCoins' => 45,
            'ruudByCharacter' => ['Alpha' => 5000, 'Zeta' => 6000],
        ], $snapshot->toArray());
        self::assertSame($before, $this->storedBalances($connection));
    }

    /**
     * Confirms characters remain visible before their account coin rows exist.
     */
    public function test_returns_zero_uninitialized_coins_without_losing_character_ruud(): void
    {
        $connection = $this->createGameSchema();
        $connection->table('Character')->insert([
            'CharacterIndex' => 1,
            'AccountID' => 'newplayer',
            'Name' => 'NewHero',
            'RuudMoney' => 125,
        ]);

        self::assertSame([
            'wCoinC' => 0,
            'wCoinP' => 0,
            'goblinPoints' => 0,
            'luckyCoins' => 0,
            'ruudByCharacter' => ['NewHero' => 125],
        ], $this->repository()->forAccount('newplayer')->toArray());
    }

    /**
     * Confirms malformed legacy amounts normalize without repairing game rows.
     */
    public function test_normalizes_null_and_negative_native_balances_only_in_the_snapshot(): void
    {
        $connection = $this->createGameSchema();
        $connection->table('CashShopData')->insert([
            'AccountID' => 'broken', 'WCoinC' => -10, 'WCoinP' => null, 'GoblinPoint' => -30,
        ]);
        $connection->table('LuckyCoin')->insert(['AccountID' => 'broken', 'LuckyCoin' => -40]);
        $connection->table('Character')->insert([
            ['CharacterIndex' => 1, 'AccountID' => 'broken', 'Name' => 'Negative', 'RuudMoney' => -50],
            ['CharacterIndex' => 2, 'AccountID' => 'broken', 'Name' => 'NullRuud', 'RuudMoney' => null],
        ]);
        $before = $this->storedBalances($connection);

        self::assertSame([
            'wCoinC' => 0,
            'wCoinP' => 0,
            'goblinPoints' => 0,
            'luckyCoins' => 0,
            'ruudByCharacter' => ['Negative' => 0, 'NullRuud' => 0],
        ], $this->repository()->forAccount('broken')->toArray());
        self::assertSame($before, $this->storedBalances($connection));
    }

    /**
     * Confirms an account identifier is bound as data rather than SQL syntax.
     */
    public function test_does_not_read_other_accounts_for_a_sql_shaped_identifier(): void
    {
        $connection = $this->createGameSchema();
        $connection->table('CashShopData')->insert([
            'AccountID' => 'owner', 'WCoinC' => 1, 'WCoinP' => 2, 'GoblinPoint' => 3,
        ]);
        $connection->table('LuckyCoin')->insert(['AccountID' => 'owner', 'LuckyCoin' => 4]);
        $connection->table('Character')->insert([
            'CharacterIndex' => 1, 'AccountID' => 'owner', 'Name' => 'Private', 'RuudMoney' => 5,
        ]);

        self::assertSame([
            'wCoinC' => 0,
            'wCoinP' => 0,
            'goblinPoints' => 0,
            'luckyCoins' => 0,
            'ruudByCharacter' => [],
        ], $this->repository()->forAccount("' OR 1=1--")->toArray());
    }

    /**
     * Creates only the S21 currency columns inside an isolated in-memory database.
     */
    private function createGameSchema(): ConnectionInterface
    {
        config()->set('database.connections.game', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $database = $this->app->make(DatabaseManager::class);

        $database->purge('game');

        $connection = $database->connection('game');

        $connection->statement('CREATE TABLE CashShopData (AccountID TEXT PRIMARY KEY, WCoinC INTEGER NULL, WCoinP INTEGER NULL, GoblinPoint INTEGER NULL)');
        $connection->statement('CREATE TABLE LuckyCoin (AccountID TEXT PRIMARY KEY, LuckyCoin INTEGER NULL)');
        $connection->statement('CREATE TABLE Character (CharacterIndex INTEGER PRIMARY KEY, AccountID TEXT NOT NULL, Name TEXT NOT NULL, RuudMoney INTEGER NULL)');

        return $connection;
    }

    /**
     * Captures stored balances to prove display reads never mutate game records.
     *
     * @return array{cashShop: list<array<string, mixed>>, luckyCoin: list<array<string, mixed>>, characters: list<array<string, mixed>>}
     */
    private function storedBalances(ConnectionInterface $connection): array
    {
        return [
            'cashShop' => array_map('get_object_vars', $connection->table('CashShopData')->orderBy('AccountID')->get()->all()),
            'luckyCoin' => array_map('get_object_vars', $connection->table('LuckyCoin')->orderBy('AccountID')->get()->all()),
            'characters' => array_map('get_object_vars', $connection->table('Character')->orderBy('CharacterIndex')->get()->all()),
        ];
    }

    /**
     * Constructs the S21 repository without selecting an incomplete game profile.
     */
    private function repository(): SqlServerMuDevsSeason21CurrencyBalanceRepository
    {
        return new SqlServerMuDevsSeason21CurrencyBalanceRepository(
            $this->app->make(DatabaseManager::class),
        );
    }
}
