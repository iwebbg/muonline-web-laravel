<?php

namespace Tests\Feature;

use App\Game\Data\MasterProgression;
use App\Game\Repositories\SqlServerMuDevsSeason21MasterProgressionRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Verifies S21 progression uses stable character indexes instead of stale cached names.
 */
final class MuDevsSeason21MasterProgressionRepositoryTest extends TestCase
{
    /**
     * Confirms stale names cannot leak another character's progression into an account.
     */
    public function test_reads_owned_master_and_enhancement_counters_by_character_index(): void
    {
        $connection = $this->createGameSchema();
        $connection->table('Character')->insert([
            ['AccountID' => 'owner', 'Name' => 'Owned', 'CharacterIndex' => 101, 'Class' => 16],
            ['AccountID' => 'other', 'Name' => 'Hidden', 'CharacterIndex' => 202, 'Class' => 240],
        ]);
        $connection->table('MasterSkillTree')->insert([
            ['Name' => 'Hidden', 'CharacterIndex' => 101, 'MasterLevel' => 500, 'MasterPoint' => 10, 'MasterExperience' => 9_000_000_000],
            ['Name' => 'Owned', 'CharacterIndex' => 202, 'MasterLevel' => 900, 'MasterPoint' => 99, 'MasterExperience' => 1],
        ]);
        $connection->table('EnhanceSkillTree')->insert([
            ['Name' => 'Hidden', 'CharacterIndex' => 101, 'EnhancePoint' => 23],
            ['Name' => 'Owned', 'CharacterIndex' => 202, 'EnhancePoint' => 999],
        ]);

        $progression = $this->repository()->forAccount('owner');

        self::assertSame(['Owned'], array_keys($progression));
        self::assertSame([
            'level' => 500, 'availablePoints' => 10, 'experience' => 9_000_000_000,
            'enhancementPoints' => 23,
        ], $progression['Owned']->toArray());
        self::assertSame([], $this->repository()->forAccount("' OR 1=1--"));
        self::assertSame(999, (int) $connection->table('EnhanceSkillTree')->where('CharacterIndex', 202)->value('EnhancePoint'));
    }

    /**
     * Confirms public reads omit absent enhancement data and reject missing characters.
     */
    public function test_reads_public_progression_without_fabricating_missing_enhancement_points(): void
    {
        $connection = $this->createGameSchema();
        $connection->table('Character')->insert([
            'AccountID' => 'owner', 'Name' => 'Public', 'CharacterIndex' => 101, 'Class' => 16,
        ]);
        $connection->table('MasterSkillTree')->insert([
            'Name' => 'OldName', 'CharacterIndex' => 101,
            'MasterLevel' => -5, 'MasterPoint' => null, 'MasterExperience' => -10,
        ]);
        config()->set('game.database_connections.rankings', '');

        $progression = $this->repository()->forCharacter('Public');

        self::assertSame(['level' => 0, 'availablePoints' => 0, 'experience' => 0], $progression?->toArray());
        self::assertNull($this->repository()->forCharacter('missing'));
        self::assertSame(
            ['level' => 1, 'availablePoints' => 2, 'experience' => 3],
            (new MasterProgression('Legacy', 1, 2, 3))->toArray(),
        );
    }

    /**
     * Confirms zero enhancement points differ from an absent native enhancement row.
     */
    public function test_normalizes_negative_enhancement_points_without_hiding_an_initialized_tree(): void
    {
        $connection = $this->createGameSchema();
        $connection->table('Character')->insert([
            'AccountID' => 'owner', 'Name' => 'Enhanced', 'CharacterIndex' => 1, 'Class' => 16,
        ]);
        $connection->table('MasterSkillTree')->insert(['Name' => 'Enhanced', 'CharacterIndex' => 1]);
        $connection->table('EnhanceSkillTree')->insert(['Name' => 'Enhanced', 'CharacterIndex' => 1, 'EnhancePoint' => -9]);

        self::assertSame(0, $this->repository()->forAccount('owner')['Enhanced']->enhancementPoints);
    }

    /**
     * Loads the sanitized portable schema into the isolated game connection.
     */
    private function createGameSchema(): ConnectionInterface
    {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));

        return $connection;
    }

    /**
     * Builds the progression reader against isolated test connections.
     */
    private function repository(): SqlServerMuDevsSeason21MasterProgressionRepository
    {
        return new SqlServerMuDevsSeason21MasterProgressionRepository($this->app->make(DatabaseManager::class));
    }
}
