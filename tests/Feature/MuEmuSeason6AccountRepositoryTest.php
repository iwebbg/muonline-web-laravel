<?php

namespace Tests\Feature;

use App\Game\Repositories\SqlServerMuEmuSeason6AccountReadRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6AccountRegistrationRepository;
use App\Game\Versions\MuEmuSeason6\MapNameMapperMuEmuSeason6;
use App\Game\Versions\MuEmuSeason6\MuEmuSeason6Profile;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Verifies private account reads and registration against the MuEMU column contract.
 */
final class MuEmuSeason6AccountRepositoryTest extends TestCase
{
    /**
     * Creates an isolated database matching the consumed MuEMU account columns.
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
            CREATE TABLE MEMB_INFO (
                memb_guid INTEGER PRIMARY KEY AUTOINCREMENT,
                memb___id TEXT NOT NULL UNIQUE,
                memb__pwd TEXT NOT NULL,
                memb_name TEXT NOT NULL,
                sno__numb TEXT NOT NULL,
                mail_addr TEXT NULL,
                bloc_code TEXT NOT NULL,
                ctl1_code TEXT NOT NULL,
                AccountLevel INTEGER NOT NULL DEFAULT 0,
                AccountExpireDate TEXT NOT NULL DEFAULT '1900-01-01',
                WarehouseCount INTEGER NOT NULL DEFAULT 0
            )
        SQL);
        $connection->statement(<<<'SQL'
            CREATE TABLE Character (
                AccountID TEXT NOT NULL,
                Name TEXT PRIMARY KEY,
                cLevel INTEGER NULL,
                LevelUpPoint INTEGER NULL,
                Class INTEGER NULL,
                Strength INTEGER NULL,
                Dexterity INTEGER NULL,
                Vitality INTEGER NULL,
                Energy INTEGER NULL,
                Leadership INTEGER NULL,
                Money INTEGER NULL,
                MapNumber INTEGER NULL,
                ResetCount INTEGER NOT NULL,
                MasterResetCount INTEGER NOT NULL
            )
        SQL);
    }

    /**
     * Confirms native reset columns, Rage Fighter stats and Season 6 maps are normalized.
     */
    public function test_reads_owned_muemu_character_data(): void
    {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->table('MEMB_STAT')->insert([
            'memb___id' => 'owner',
            'ConnectStat' => 1,
        ]);
        $connection->table('Character')->insert([
            'AccountID' => 'owner',
            'Name' => 'Fighter',
            'cLevel' => 400,
            'LevelUpPoint' => 125,
            'Class' => 98,
            'Strength' => 1_500,
            'Dexterity' => 900,
            'Vitality' => 1_100,
            'Energy' => 700,
            'Leadership' => 0,
            'Money' => 12_000_000,
            'MapNumber' => 81,
            'ResetCount' => 25,
            'MasterResetCount' => 2,
        ]);

        $character = $this->accountReadRepository()->characterFor('owner', 'Fighter');

        self::assertNotNull($character);
        self::assertSame('Fist Master', $character->className);
        self::assertSame(25, $character->resets);
        self::assertSame(2, $character->grandResets);
        self::assertSame('Karutan 2', $character->map);
        self::assertTrue($character->online);
        self::assertSame([], $character->additionalStats);
    }

    /**
     * Confirms registration follows MuEMU's disabled-MD5 JoinServer configuration.
     */
    public function test_registers_plaintext_muemu_account_without_billing_row(): void
    {
        $database = $this->app->make(DatabaseManager::class);
        $repository = new SqlServerMuEmuSeason6AccountRegistrationRepository($database);

        $result = $repository->register('newuser', 'secret123', 'new@example.com');
        $account = $database->connection('game')
            ->table('MEMB_INFO')
            ->where('memb___id', 'newuser')
            ->first();

        self::assertNotNull($account);
        self::assertSame('secret123', $account->memb__pwd);
        self::assertSame('new@example.com', $account->mail_addr);
        self::assertSame(0, $account->AccountLevel);
        self::assertSame('newuser', $result->accountId);
        self::assertFalse($result->billingRecordCreated);
    }

    /**
     * Builds the private read adapter with the MuEMU profile and map source.
     */
    private function accountReadRepository(): SqlServerMuEmuSeason6AccountReadRepository
    {
        return new SqlServerMuEmuSeason6AccountReadRepository(
            $this->app->make(DatabaseManager::class),
            new MuEmuSeason6Profile,
            new MapNameMapperMuEmuSeason6,
        );
    }
}
