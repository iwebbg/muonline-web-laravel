<?php

namespace Tests\Integration1_0M\Game\Repositories;

use App\Game\Repositories\SqlServer1_0MAccountCredentialRepository;
use App\Game\Repositories\SqlServer1_0MAccountPasswordRecoveryRepository;
use App\Game\Repositories\SqlServer1_0MAccountReadRepository;
use App\Game\Repositories\SqlServer1_0MAccountRegistrationRepository;
use App\Game\Versions\Version1_0M\DarksTeamMd5PasswordCodec;
use App\Game\Versions\Version1_0M\MapNameMapper1_0M;
use App\Game\Versions\Version1_0M\Version1_0MProfile;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Tests\Integration\TestCase;

/**
 * Verifies account adapters against the disposable sanitized DarksTeam 1.0M schema.
 */
final class SqlServer1_0MAccountRepositoryTest extends TestCase
{
    private const ACCOUNT_ID = 'int1mtest';

    private const CHARACTER_NAME = 'IntDarkLrd';

    /**
     * Removes only records owned by this integration test.
     */
    protected function tearDown(): void
    {
        if ($this->app !== null) {
            $connection = $this->gameConnection();
            $connection->table('Character')->where('AccountID', self::ACCOUNT_ID)->delete();
            $connection->table('MEMB_STAT')->where('memb___id', self::ACCOUNT_ID)->delete();
            $connection->table('MEMB_INFO')->where('memb___id', self::ACCOUNT_ID)->delete();
        }

        parent::tearDown();
    }

    /**
     * Confirms the fixture exposes the version-specific inventory and Leadership fields.
     */
    public function test_sanitized_schema_contains_required_1_0m_columns(): void
    {
        $characterColumns = $this->columnNames('Character');

        $this->assertContains('Inventory', $characterColumns);
        $this->assertContains('Leadership', $characterColumns);
        $this->assertContains('Resets', $characterColumns);
        $this->assertContains('GrandResets', $characterColumns);

        $inventoryLength = $this->gameConnection()->selectOne(
            'SELECT CHARACTER_MAXIMUM_LENGTH AS length FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['dbo', 'Character', 'Inventory'],
        );

        $this->assertSame(1_080, (int) $inventoryLength->length);
    }

    /**
     * Confirms a Dark Lord read preserves Command and the custom GrandResets column.
     */
    public function test_reads_dark_lord_command_and_grand_resets(): void
    {
        $connection = $this->gameConnection();
        $connection->table('MEMB_STAT')->insert([
            'memb___id' => self::ACCOUNT_ID,
            'ConnectStat' => 0,
        ]);
        $connection->table('Character')->insert([
            'AccountID' => self::ACCOUNT_ID,
            'Name' => self::CHARACTER_NAME,
            'cLevel' => 350,
            'LevelUpPoint' => 125,
            'Class' => 64,
            'Strength' => 700,
            'Dexterity' => 600,
            'Vitality' => 500,
            'Energy' => 400,
            'Leadership' => 1_200,
            'Money' => 9_000_000,
            'MapNumber' => 30,
            'Resets' => 12,
            'GrandResets' => 3,
        ]);

        $repository = new SqlServer1_0MAccountReadRepository(
            database: $this->app->make(DatabaseManager::class),
            versionProfile: new Version1_0MProfile,
            mapNameMapper: new MapNameMapper1_0M,
        );

        $character = $repository->characterFor(self::ACCOUNT_ID, self::CHARACTER_NAME);

        $this->assertNotNull($character);
        $this->assertSame('Dark Lord', $character->className);
        $this->assertSame(3, $character->grandResets);
        $this->assertSame('Valley of Loren', $character->map);
        $this->assertFalse($character->online);
        $this->assertSame(['command' => 1_200], $character->additionalStats);
    }

    /**
     * Confirms registration stores a 16-byte account-bound hash without VI_CURR_INFO.
     */
    public function test_registers_account_with_game_server_compatible_hash(): void
    {
        $repository = new SqlServer1_0MAccountRegistrationRepository(
            database: $this->app->make(DatabaseManager::class),
            passwordCodec: $this->passwordCodec(),
        );

        $result = $repository->register(
            username: self::ACCOUNT_ID,
            password: 'Password1',
            email: 'integration@example.test',
        );
        $account = $this->gameConnection()
            ->table('MEMB_INFO')
            ->where('memb___id', self::ACCOUNT_ID)
            ->first(['memb__pwd']);

        $this->assertSame(self::ACCOUNT_ID, $result->accountId);
        $this->assertFalse($result->billingRecordCreated);
        $this->assertSame(16, strlen($account->memb__pwd));
        $this->assertSame(
            'EB2A61335FDAEDE4C5105E6ACED47FC7',
            strtoupper(bin2hex((string) $account->memb__pwd)),
        );
    }

    /**
     * Confirms credential changes keep binary hashes inside SQL Server.
     */
    public function test_updates_email_and_password_with_adapter_hashing(): void
    {
        $this->registrationRepository()->register(
            username: self::ACCOUNT_ID,
            password: 'Password1',
            email: 'before@example.test',
        );
        $repository = new SqlServer1_0MAccountCredentialRepository(
            database: $this->app->make(DatabaseManager::class),
            passwordCodec: $this->passwordCodec(),
        );

        $result = $repository->update(
            accountId: self::ACCOUNT_ID,
            currentPassword: 'Password1',
            email: 'after@example.test',
            newPassword: 'Password2',
        );
        $account = $this->gameConnection()
            ->table('MEMB_INFO')
            ->where('memb___id', self::ACCOUNT_ID)
            ->first(['mail_addr', 'memb__pwd']);

        $this->assertTrue($result->emailChanged);
        $this->assertTrue($result->passwordChanged);
        $this->assertSame('after@example.test', (string) $account->mail_addr);
        $this->assertSame(
            '6121E72B66CF48766EFF6015B79377A2',
            strtoupper(bin2hex((string) $account->memb__pwd)),
        );
    }

    /**
     * Confirms password recovery also writes the binary hash inside SQL Server.
     */
    public function test_recovers_password_with_adapter_hashing(): void
    {
        $this->registrationRepository()->register(
            username: self::ACCOUNT_ID,
            password: 'Password1',
            email: 'recovery@example.test',
        );
        $repository = new SqlServer1_0MAccountPasswordRecoveryRepository(
            database: $this->app->make(DatabaseManager::class),
            passwordCodec: $this->passwordCodec(),
        );

        $repository->replacePassword(self::ACCOUNT_ID, 'Recovered1');
        $passwordHash = $this->gameConnection()
            ->table('MEMB_INFO')
            ->where('memb___id', self::ACCOUNT_ID)
            ->value('memb__pwd');

        $this->assertSame(
            '178789E2508D346610A5F7F5FA2B5657',
            strtoupper(bin2hex((string) $passwordHash)),
        );
    }

    /**
     * Creates the production registration adapter against the disposable database.
     */
    private function registrationRepository(): SqlServer1_0MAccountRegistrationRepository
    {
        return new SqlServer1_0MAccountRegistrationRepository(
            database: $this->app->make(DatabaseManager::class),
            passwordCodec: $this->passwordCodec(),
        );
    }

    /**
     * Creates the exact DarksTeam password codec used by production adapters.
     */
    private function passwordCodec(): DarksTeamMd5PasswordCodec
    {
        return new DarksTeamMd5PasswordCodec;
    }

    /**
     * Returns the disposable legacy game connection.
     */
    private function gameConnection(): ConnectionInterface
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }

    /**
     * Lists one legacy table's columns from SQL Server metadata.
     *
     * @return list<string>
     */
    private function columnNames(string $table): array
    {
        $rows = $this->gameConnection()->select(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            ['dbo', $table],
        );

        return array_map(
            static fn (object $row): string => (string) $row->COLUMN_NAME,
            $rows,
        );
    }
}
