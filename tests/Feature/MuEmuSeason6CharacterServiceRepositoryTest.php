<?php

namespace Tests\Feature;

use App\CharacterServices\PurchasedResetPolicy;
use App\Game\Repositories\SqlServerMuEmuSeason6CharacterServiceRepository;
use App\Game\Versions\MuEmuSeason6\MuEmuSeason6Profile;
use App\Reset\InventoryPolicy;
use App\Reset\PointsMode;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Verifies paid services use MuEMU reset and character-slot columns.
 */
final class MuEmuSeason6CharacterServiceRepositoryTest extends TestCase
{
    /**
     * Creates the exact subset of the Update 32 schema consumed by paid services.
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

        $connection->statement('CREATE TABLE MEMB_STAT (memb___id TEXT PRIMARY KEY, ConnectStat INTEGER NULL)');
        $connection->statement(<<<'SQL'
            CREATE TABLE Character (
                AccountID TEXT NOT NULL,
                Name TEXT PRIMARY KEY,
                Class INTEGER NOT NULL,
                MarryName TEXT NULL,
                cLevel INTEGER NULL,
                LevelUpPoint INTEGER NULL,
                Experience INTEGER NULL,
                Strength INTEGER NULL,
                Dexterity INTEGER NULL,
                Vitality INTEGER NULL,
                Energy INTEGER NULL,
                Leadership INTEGER NULL,
                Money INTEGER NULL,
                ResetCount INTEGER NULL,
                MasterResetCount INTEGER NULL
            )
        SQL);
        $connection->statement(<<<'SQL'
            CREATE TABLE AccountCharacter (
                Id TEXT PRIMARY KEY,
                GameID1 TEXT NULL, GameID2 TEXT NULL, GameID3 TEXT NULL,
                GameID4 TEXT NULL, GameID5 TEXT NULL, GameID6 TEXT NULL,
                GameID7 TEXT NULL, GameID8 TEXT NULL, GameID9 TEXT NULL,
                GameID10 TEXT NULL, GameIDC TEXT NULL
            )
        SQL);
        $connection->statement('CREATE TABLE Guild (G_Name TEXT PRIMARY KEY, G_Master TEXT NULL)');
        $connection->statement('CREATE TABLE GuildMember (Name TEXT PRIMARY KEY, G_Name TEXT NOT NULL)');
    }

    /**
     * Confirms rename updates character slots beyond the five-slot legacy layouts.
     */
    public function test_rename_updates_tenth_character_slot(): void
    {
        $connection = $this->game();

        $this->insertOfflineCharacter('owner', 'OldFighter');
        $connection->table('AccountCharacter')->insert([
            'Id' => 'owner',
            'GameID10' => 'OldFighter',
            'GameIDC' => 'OldFighter',
        ]);

        $result = $this->repository()->rename('owner', 'OldFighter', 'NewFighter');
        $slots = $connection->table('AccountCharacter')->where('Id', 'owner')->first();

        self::assertSame('NewFighter', $slots->GameID10);
        self::assertSame('NewFighter', $slots->GameIDC);
        self::assertSame('OldFighter', $result->before['name']);
        self::assertSame('NewFighter', $result->after['name']);
    }

    /**
     * Confirms purchased packages mutate ResetCount without legacy counter aliases.
     */
    public function test_reset_purchase_uses_native_reset_count(): void
    {
        $this->insertOfflineCharacter('owner', 'ResetFighter', resetCount: 2);
        $policy = new PurchasedResetPolicy(
            maximumResets: 999,
            clearStats: true,
            pointsMode: PointsMode::Cumulative,
            pointsPerReset: 500,
            inventoryPolicy: InventoryPolicy::KeepAll,
        );

        $result = $this->repository()->purchaseResets('owner', 'ResetFighter', 5, $policy);
        $character = $this->game()->table('Character')->where('Name', 'ResetFighter')->first();

        self::assertSame(7, $character->ResetCount);
        self::assertSame(3_500, $character->LevelUpPoint);
        self::assertSame(1, $character->cLevel);
        self::assertSame(7, $result->after['resets']);
    }

    /**
     * Persists one native offline Rage Fighter record.
     */
    private function insertOfflineCharacter(
        string $accountId,
        string $characterName,
        int $resetCount = 0,
    ): void {
        $this->game()->table('MEMB_STAT')->insert([
            'memb___id' => $accountId,
            'ConnectStat' => 0,
        ]);
        $this->game()->table('Character')->insert([
            'AccountID' => $accountId,
            'Name' => $characterName,
            'Class' => 96,
            'MarryName' => null,
            'cLevel' => 400,
            'LevelUpPoint' => 0,
            'Experience' => 1_000,
            'Strength' => 100,
            'Dexterity' => 100,
            'Vitality' => 100,
            'Energy' => 100,
            'Leadership' => 0,
            'Money' => 5_000_000,
            'ResetCount' => $resetCount,
            'MasterResetCount' => 0,
        ]);
    }

    /**
     * Returns the isolated game connection.
     */
    private function game(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }

    /**
     * Builds the MuEMU paid-service repository.
     */
    private function repository(): SqlServerMuEmuSeason6CharacterServiceRepository
    {
        return new SqlServerMuEmuSeason6CharacterServiceRepository(
            $this->app->make(DatabaseManager::class),
            new MuEmuSeason6Profile,
        );
    }
}
