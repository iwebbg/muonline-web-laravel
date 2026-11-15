<?php

namespace Tests\IntegrationSeason21;

use App\Game\Repositories\SqlServerMuDevsSeason21BattleCoreReadRepository;
use Illuminate\Database\DatabaseManager;
use Tests\Integration\TestCase;

/**
 * Verifies native Battle Core filtering against only the isolated SQL Server service.
 */
final class MuDevsSeason21BattleCoreSqlReadTest extends TestCase
{
    /**
     * Retains account/realm ownership and native numeric types through real SQL Server reads.
     */
    public function test_account_snapshot_filters_other_realms_and_claimed_rewards_on_sql_server(): void
    {
        $database = $this->app->make(DatabaseManager::class);
        $admin = $database->connection('game');
        $admin->statement('CREATE DATABASE [BattleCore_S21_Read_Test]');
        config()->set('database.connections.battle_core', config('database.connections.game'));
        config()->set('database.connections.battle_core.database', 'BattleCore_S21_Read_Test');
        $database->purge('battle_core');
        $connection = $database->connection('battle_core');
        config()->set('game.battle_core.reads_enabled', true);
        config()->set('game.battle_core.server_codes', [20]);
        $connection->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-battle-core-read-schema.sql')));

        try {
            $connection->table('BattleCoreUser')->insert([
                ['UniqueKey' => 1, 'AccountID' => 'owner', 'Name' => 'OwnHero', 'ServerCode' => 20, 'RegisterState' => 1, 'RegisterMonth' => 11, 'RegisterDay' => 15],
                ['UniqueKey' => 2, 'AccountID' => 'owner', 'Name' => 'OtherRealm', 'ServerCode' => 99, 'RegisterState' => 1, 'RegisterMonth' => 11, 'RegisterDay' => 15],
            ]);
            $connection->table('BattleCoreReward')->insert([
                ['id' => 1, 'AccountID' => 'owner', 'RealName' => 'OwnHero', 'ServerCode' => 20, 'ItemCount' => 3, 'TookState' => 0],
                ['id' => 2, 'AccountID' => 'owner', 'RealName' => 'OwnHero', 'ServerCode' => 20, 'ItemCount' => 9, 'TookState' => 1],
                ['id' => 3, 'AccountID' => 'owner', 'RealName' => 'OtherRealm', 'ServerCode' => 99, 'ItemCount' => 8, 'TookState' => 0],
            ]);

            $snapshot = $this->app->make(SqlServerMuDevsSeason21BattleCoreReadRepository::class)->forAccount('owner');

            self::assertTrue($snapshot->available);
            self::assertSame([['name' => 'OwnHero', 'registered' => true, 'month' => 11, 'day' => 15]], $snapshot->registrations);
            self::assertSame(3, $snapshot->unclaimedRewardItems);
            self::assertSame(2, $connection->table('BattleCoreUser')->count());
            self::assertSame(3, $connection->table('BattleCoreReward')->count());
        } finally {
            $database->purge('battle_core');
            $admin->statement('DROP DATABASE [BattleCore_S21_Read_Test]');
        }
    }
}
