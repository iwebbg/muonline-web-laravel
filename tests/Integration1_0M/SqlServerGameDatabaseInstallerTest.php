<?php

namespace Tests\Integration1_0M;

use App\Installation\GameDatabaseInstallation;
use App\Installation\SqlServerGameDatabaseInstaller;
use Illuminate\Database\DatabaseManager;
use PDO;
use Tests\Integration\TestCase;

/**
 * Verifies restricted game login provisioning against the disposable 1.0M SQL Server.
 */
final class SqlServerGameDatabaseInstallerTest extends TestCase
{
    private const LOGIN_NAME = 'integration_game_web';

    private const LOGIN_PASSWORD = 'Integration-game-Web-123!';

    private const ROLE_NAME = 'integration_game_web_role';

    /**
     * Points both bootstrap and runtime connections at the disposable SQL Server.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $databaseName = (string) config('database.connections.game.database');
        $host = (string) config('database.connections.game.host');
        $port = (string) config('database.connections.game.port');

        config()->set('game.database_bootstrap.admin_connection', 'game_admin');
        config()->set('database.connections.game_admin.host', $host);
        config()->set('database.connections.game_admin.port', $port);
        config()->set('database.connections.game_admin.database', 'master');
        config()->set('database.connections.game_admin.username', 'sa');
        config()->set('database.connections.game_admin.password', 'Integration-only_Password123');
        config()->set('database.connections.game_admin.encrypt', 'no');
        config()->set('database.connections.game_admin.trust_server_certificate', 'true');
        config()->set('database.connections.game.username', self::LOGIN_NAME);
        config()->set('database.connections.game.password', self::LOGIN_PASSWORD);
        config()->set('database.connections.game.database', $databaseName);

        $this->database()->purge('game_admin');
        $this->database()->purge('game');
    }

    /**
     * Removes only the temporary SQL principals created by this integration test.
     */
    protected function tearDown(): void
    {
        if ($this->app !== null) {
            $database = $this->database();
            $database->purge('game');
            $pdo = $database->connection('game_admin')->getPdo();
            $databaseName = $this->quoteIdentifier(
                (string) config('database.connections.game.database'),
            );
            $loginName = $this->quoteIdentifier(self::LOGIN_NAME);

            $pdo->exec("USE {$databaseName}");

            if ($this->scalar($pdo, 'SELECT USER_ID(?)', [self::LOGIN_NAME]) !== null) {
                $pdo->exec("DROP USER {$loginName}");
            }

            $pdo->exec('USE [master]');

            if ($this->scalar($pdo, 'SELECT SUSER_ID(?)', [self::LOGIN_NAME]) !== null) {
                $pdo->exec("DROP LOGIN {$loginName}");
            }

            $database->purge('game_admin');
        }

        parent::tearDown();
    }

    /**
     * Confirms repeated installation creates a usable login with only adapter permissions.
     */
    public function test_installs_restricted_game_access_idempotently(): void
    {
        $installation = new GameDatabaseInstallation(
            databaseName: (string) config('database.connections.game.database'),
            loginName: self::LOGIN_NAME,
            loginPassword: self::LOGIN_PASSWORD,
            roleName: self::ROLE_NAME,
        );
        $installer = $this->app->make(SqlServerGameDatabaseInstaller::class);

        $installer->install($installation);
        $installer->install($installation);

        $this->artisan('game:doctor')
            ->expectsOutputToContain('Required game profile schema is available')
            ->expectsOutputToContain('Game database diagnostics passed')
            ->assertSuccessful();

        $runtimeConnection = $this->database()->connection('game');
        $identity = $runtimeConnection->selectOne(
            'SELECT SUSER_SNAME() AS login_name, USER_NAME() AS user_name',
        );
        $permissions = $runtimeConnection->selectOne(
            'SELECT '
            .'HAS_PERMS_BY_NAME(?, ?, ?) AS can_read_characters, '
            .'HAS_PERMS_BY_NAME(?, ?, ?) AS can_update_characters, '
            .'HAS_PERMS_BY_NAME(?, ?, ?) AS can_update_status, '
            .'HAS_PERMS_BY_NAME(?, ?, ?) AS can_execute_serial',
            [
                'dbo.Character', 'OBJECT', 'SELECT',
                'dbo.Character', 'OBJECT', 'UPDATE',
                'dbo.MEMB_STAT', 'OBJECT', 'UPDATE',
                'dbo.WZ_GetItemSerial', 'OBJECT', 'EXECUTE',
            ],
        );

        $this->assertSame(self::LOGIN_NAME, $identity->login_name);
        $this->assertSame(self::LOGIN_NAME, $identity->user_name);
        $this->assertSame(1, (int) $permissions->can_read_characters);
        $this->assertSame(1, (int) $permissions->can_update_characters);
        $this->assertSame(0, (int) $permissions->can_update_status);
        $this->assertSame(1, (int) $permissions->can_execute_serial);
    }

    /**
     * Resolves Laravel's database manager from the integration application.
     */
    private function database(): DatabaseManager
    {
        return $this->app->make(DatabaseManager::class);
    }

    /**
     * Reads one scalar SQL Server result with bound parameters.
     *
     * @param  list<string>  $bindings
     */
    private function scalar(PDO $pdo, string $query, array $bindings): mixed
    {
        $statement = $pdo->prepare($query);
        $statement->execute($bindings);
        $value = $statement->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * Quotes a trusted integration-test SQL Server identifier.
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '['.str_replace(']', ']]', $identifier).']';
    }
}
