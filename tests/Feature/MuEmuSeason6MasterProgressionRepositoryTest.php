<?php

namespace Tests\Feature;

use App\Game\Repositories\SqlServerMuEmuSeason6MasterProgressionRepository;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Verifies account-scoped Master progression reads against the Season 6 schema.
 */
final class MuEmuSeason6MasterProgressionRepositoryTest extends TestCase
{
    /**
     * Creates isolated character and Master progression tables.
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

        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY)');
        $connection->statement('CREATE TABLE MasterSkillTree (Name TEXT PRIMARY KEY, MasterLevel INTEGER NULL, MasterPoint INTEGER NULL, MasterExperience INTEGER NULL, MasterSkill BLOB NULL)');
    }

    /**
     * Confirms the adapter returns only progression owned by the requested account.
     */
    public function test_returns_only_owned_master_progression_keyed_by_character(): void
    {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->table('Character')->insert([
            ['AccountID' => 'owner', 'Name' => 'OwnedMaster'],
            ['AccountID' => 'other', 'Name' => 'HiddenMaster'],
        ]);
        $connection->table('MasterSkillTree')->insert([
            [
                'Name' => 'OwnedMaster',
                'MasterLevel' => 125,
                'MasterPoint' => 17,
                'MasterExperience' => 9_876_543,
                'MasterSkill' => hex2bin(str_repeat('00', 180)),
            ],
            [
                'Name' => 'HiddenMaster',
                'MasterLevel' => 400,
                'MasterPoint' => 99,
                'MasterExperience' => 123_456_789,
                'MasterSkill' => hex2bin(str_repeat('00', 180)),
            ],
        ]);

        $progression = $this->repository()->forAccount('owner');

        $this->assertSame(['OwnedMaster'], array_keys($progression));
        $this->assertSame(125, $progression['OwnedMaster']->level);
        $this->assertSame(17, $progression['OwnedMaster']->availablePoints);
        $this->assertSame(9_876_543, $progression['OwnedMaster']->experience);
    }

    /**
     * Confirms malformed negative legacy counters are not exposed to players.
     */
    public function test_normalizes_negative_master_progression_to_zero(): void
    {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->table('Character')->insert(['AccountID' => 'owner', 'Name' => 'BrokenMaster']);
        $connection->table('MasterSkillTree')->insert([
            'Name' => 'BrokenMaster',
            'MasterLevel' => -1,
            'MasterPoint' => -2,
            'MasterExperience' => -3,
            'MasterSkill' => null,
        ]);

        $progression = $this->repository()->forAccount('owner');

        $this->assertSame(0, $progression['BrokenMaster']->level);
        $this->assertSame(0, $progression['BrokenMaster']->availablePoints);
        $this->assertSame(0, $progression['BrokenMaster']->experience);
    }

    /**
     * Builds the adapter under test with the isolated game connection.
     */
    private function repository(): SqlServerMuEmuSeason6MasterProgressionRepository
    {
        return new SqlServerMuEmuSeason6MasterProgressionRepository(
            $this->app->make(DatabaseManager::class),
        );
    }
}
