<?php

namespace Tests\Feature;

use App\Game\Repositories\SqlServerMuDevsSeason21AccountReadRepository;
use App\Game\Versions\MuDevsSeason21\MapNameMapperMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\MuDevsSeason21Profile;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Verifies ownership, class metadata and offline status in S21 private character reads.
 */
final class MuDevsSeason21AccountReadRepositoryTest extends TestCase
{
    /**
     * Confirms all native base families render with owned stats and bundled portraits.
     */
    public function test_reads_all_owned_base_families_and_excludes_other_accounts(): void
    {
        $connection = $this->createGameSchema();
        $connection->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 0]);

        foreach ([0, 16, 32, 48, 64, 80, 96, 112, 128, 144, 160, 176, 192, 208, 224, 240] as $index => $classCode) {
            $connection->table('Character')->insert([
                'AccountID' => 'owner', 'Name' => sprintf('Hero%02d', $index),
                'CharacterIndex' => 100 + $index, 'Class' => $classCode,
                'cLevel' => 100, 'Strength' => 200, 'Dexterity' => 300,
                'Vitality' => 400, 'Energy' => 500, 'Leadership' => 600,
                'ResetCount' => 7, 'MasterResetCount' => 2, 'Money' => 9000,
                'LevelUpPoint' => 123, 'MapNumber' => 145,
            ]);
        }

        $connection->table('Character')->insert([
            'AccountID' => 'other', 'Name' => 'Hidden', 'CharacterIndex' => 999, 'Class' => 240,
        ]);

        $characters = $this->repository()->charactersFor('owner');

        self::assertCount(16, $characters);
        self::assertSame('Grow Lancer', $characters[7]->className);
        self::assertSame('Crusader', $characters[15]->className);
        self::assertSame('Aquilas Temple', $characters[15]->map);
        self::assertSame(7, $characters[15]->resets);
        self::assertSame(2, $characters[15]->grandResets);
        self::assertSame(9000, $characters[15]->zen);
        self::assertSame(123, $characters[15]->points);
        self::assertSame(['command' => 600], $characters[4]->additionalStats);
        self::assertSame([], $characters[15]->additionalStats);
        self::assertArrayNotHasKey('imagePosition', $characters[15]->toArray());
        self::assertSame('center top', $characters[15]->imagePosition);
        self::assertFalse($characters[15]->online);
        self::assertNull($this->repository()->characterFor('owner', 'Hidden'));
        self::assertNull($this->repository()->characterFor("' OR 1=1--", 'Hero00'));
    }

    /**
     * Confirms missing or online session state cannot be mistaken for a safe offline account.
     */
    public function test_keeps_missing_and_connected_account_status_blocked_for_game_writes(): void
    {
        $connection = $this->createGameSchema();
        $connection->table('Character')->insert([
            ['AccountID' => 'missing', 'Name' => 'NoSession', 'CharacterIndex' => 1, 'Class' => 16],
            ['AccountID' => 'online', 'Name' => 'Connected', 'CharacterIndex' => 2, 'Class' => 16],
        ]);
        $connection->table('MEMB_STAT')->insert(['memb___id' => 'online', 'ConnectStat' => 1]);

        self::assertTrue($this->repository()->characterFor('missing', 'NoSession')?->online);
        self::assertTrue($this->repository()->characterFor('online', 'Connected')?->online);
        self::assertSame([], $this->repository()->charactersFor('empty'));
    }

    /**
     * Loads only sanitized read-model tables into the isolated game connection.
     */
    private function createGameSchema(): ConnectionInterface
    {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));

        return $connection;
    }

    /**
     * Builds the S21 reader without selecting an incomplete global adapter bundle.
     */
    private function repository(): SqlServerMuDevsSeason21AccountReadRepository
    {
        return new SqlServerMuDevsSeason21AccountReadRepository(
            $this->app->make(DatabaseManager::class),
            new MuDevsSeason21Profile,
            new MapNameMapperMuDevsSeason21,
        );
    }
}
