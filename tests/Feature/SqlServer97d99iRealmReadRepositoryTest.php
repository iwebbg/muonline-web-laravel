<?php

namespace Tests\Feature;

use App\Game\Repositories\SqlServer97d99iRealmReadRepository;
use App\Game\Versions\Version97d99i\CharacterClassMapper;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Verifies the 97d+99i adapter against a sanitized portable schema fixture.
 */
final class SqlServer97d99iRealmReadRepositoryTest extends TestCase
{
    /**
     * Creates an isolated game connection with the legacy tables used by reads.
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

        $connection = $database->connection('game');

        $connection->statement('CREATE TABLE MEMB_INFO (memb___id TEXT PRIMARY KEY)');
        $connection->statement('CREATE TABLE MEMB_STAT (memb___id TEXT PRIMARY KEY, ConnectStat INTEGER NULL)');
        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, cLevel INTEGER NULL, Class INTEGER NULL, Resets INTEGER NOT NULL, GrandResets INTEGER NOT NULL)');
        $connection->statement('CREATE TABLE Guild (G_Name TEXT NOT NULL, G_Master TEXT NULL, G_Score INTEGER NULL, G_Count INTEGER NULL)');
    }

    /**
     * Confirms that overview and ranking reads normalize legacy schema values.
     */
    public function test_repository_reads_normalized_public_realm_data(): void
    {
        $database = $this->app->make(DatabaseManager::class);
        $connection = $database->connection('game');

        $connection->table('MEMB_INFO')->insert([
            ['memb___id' => 'account1'],
            ['memb___id' => 'account2'],
        ]);
        $connection->table('MEMB_STAT')->insert([
            ['memb___id' => 'account1', 'ConnectStat' => 1],
            ['memb___id' => 'account2', 'ConnectStat' => 0],
        ]);
        $connection->table('Character')->insert([
            [
                'AccountID' => 'account1',
                'Name' => 'Knight',
                'cLevel' => 400,
                'Class' => 17,
                'Resets' => 20,
                'GrandResets' => 2,
            ],
            [
                'AccountID' => 'account2',
                'Name' => 'Wizard',
                'cLevel' => 390,
                'Class' => 1,
                'Resets' => 19,
                'GrandResets' => 2,
            ],
            [
                'AccountID' => 'account2',
                'Name' => 'Elf',
                'cLevel' => 380,
                'Class' => 33,
                'Resets' => 18,
                'GrandResets' => 1,
            ],
        ]);
        $connection->table('Guild')->insert([
            [
                'G_Name' => 'Legends',
                'G_Master' => 'Knight',
                'G_Score' => 1800,
                'G_Count' => 25,
            ],
            [
                'G_Name' => 'Wizards',
                'G_Master' => 'Wizard',
                'G_Score' => 1200,
                'G_Count' => 18,
            ],
        ]);

        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $repository = new SqlServer97d99iRealmReadRepository(
            database: $database,
            classMapper: new CharacterClassMapper,
        );
        $overview = $repository->overview();
        $characters = $repository->topCharacters(2);
        $guilds = $repository->topGuilds(1);

        $this->assertSame(1, $overview->online);
        $this->assertSame(2, $overview->accounts);
        $this->assertSame(3, $overview->characters);
        $this->assertSame(2, $overview->guilds);
        $this->assertSame('database', $overview->dataSource);
        $this->assertCount(2, $characters);
        $this->assertSame('Knight', $characters[0]->name);
        $this->assertSame('Blade Knight', $characters[0]->className);
        $this->assertTrue($characters[0]->online);
        $this->assertSame('Wizard', $characters[1]->name);
        $this->assertFalse($characters[1]->online);
        $this->assertCount(1, $guilds);
        $this->assertSame('Legends', $guilds[0]->name);

        foreach ($connection->getQueryLog() as $query) {
            $normalizedQuery = strtolower(ltrim($query['query']));

            $this->assertTrue(str_starts_with($normalizedQuery, 'select'));
        }
    }

    /**
     * Confirms rankings can use a separate read-only database on newer profiles.
     */
    public function test_repository_can_route_rankings_to_named_database_connection(): void
    {
        config()->set('database.connections.ranking', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
            'readonly' => true,
        ]);
        config()->set('game.database_connections.rankings', 'ranking');

        $database = $this->app->make(DatabaseManager::class);

        $database->purge('ranking');

        $rankingConnection = $database->connection('ranking');

        $rankingConnection->statement('CREATE TABLE MEMB_STAT (memb___id TEXT PRIMARY KEY, ConnectStat INTEGER NULL)');
        $rankingConnection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, cLevel INTEGER NULL, Class INTEGER NULL, Resets INTEGER NOT NULL, GrandResets INTEGER NOT NULL)');
        $rankingConnection->statement('CREATE TABLE Guild (G_Name TEXT NOT NULL, G_Master TEXT NULL, G_Score INTEGER NULL, G_Count INTEGER NULL)');
        $rankingConnection->table('MEMB_STAT')->insert([
            'memb___id' => 'ranking-account',
            'ConnectStat' => 1,
        ]);
        $rankingConnection->table('Character')->insert([
            'AccountID' => 'ranking-account',
            'Name' => 'RankingHero',
            'cLevel' => 400,
            'Class' => 17,
            'Resets' => 99,
            'GrandResets' => 9,
        ]);
        $rankingConnection->table('Guild')->insert([
            'G_Name' => 'RankingGuild',
            'G_Master' => 'RankingHero',
            'G_Score' => 9_999,
            'G_Count' => 40,
        ]);

        $repository = new SqlServer97d99iRealmReadRepository(
            database: $database,
            classMapper: new CharacterClassMapper,
        );

        $characters = $repository->topCharacters(10);
        $guilds = $repository->topGuilds(10);

        $this->assertCount(1, $characters);
        $this->assertSame('RankingHero', $characters[0]->name);
        $this->assertTrue($characters[0]->online);
        $this->assertCount(1, $guilds);
        $this->assertSame('RankingGuild', $guilds[0]->name);
    }
}
