<?php

namespace Tests\Feature;

use App\Game\Repositories\SqlServerMuEmuSeason6LuckyCoinBalanceRepository;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Verifies account-scoped Lucky Coin reads against the Season 6 schema.
 */
final class MuEmuSeason6LuckyCoinBalanceRepositoryTest extends TestCase
{
    /**
     * Creates an isolated Lucky Coin table.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.game', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $database = $this->app->make(DatabaseManager::class);

        $database->purge('game');
        $database->connection('game')->statement(
            'CREATE TABLE LuckyCoin (AccountID TEXT PRIMARY KEY, LuckyCoin INTEGER NULL)',
        );
    }

    /**
     * Confirms balances are scoped to the requested account.
     */
    public function test_reads_only_the_requested_account_balance(): void
    {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->table('LuckyCoin')->insert([
            ['AccountID' => 'owner', 'LuckyCoin' => 42],
            ['AccountID' => 'other', 'LuckyCoin' => 900],
        ]);

        $balance = $this->repository()->balanceFor('owner');

        $this->assertSame(42, $balance);
    }

    /**
     * Confirms missing or invalid legacy balances are normalized safely.
     */
    public function test_normalizes_missing_and_negative_balances_to_zero(): void
    {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->table('LuckyCoin')->insert([
            'AccountID' => 'negative',
            'LuckyCoin' => -5,
        ]);

        $this->assertSame(0, $this->repository()->balanceFor('missing'));
        $this->assertSame(0, $this->repository()->balanceFor('negative'));
    }

    /**
     * Builds the adapter with the isolated game connection.
     */
    private function repository(): SqlServerMuEmuSeason6LuckyCoinBalanceRepository
    {
        return new SqlServerMuEmuSeason6LuckyCoinBalanceRepository(
            $this->app->make(DatabaseManager::class),
        );
    }
}
