<?php

namespace Tests\Integration;

use App\Installation\ApplicationDatabaseInstallation;
use App\Installation\ApplicationDatabaseRemoval;
use App\Installation\ApplicationDatabaseRemovalResult;
use App\Installation\SqlServerApplicationDatabaseInstaller;
use App\Installation\SqlServerApplicationDatabaseUninstaller;
use Illuminate\Database\DatabaseManager;
use PDO;

/**
 * Exercises destructive uninstall only on temporary resources inside the disposable SQL fixture.
 */
final class SqlServerApplicationDatabaseUninstallerTest extends TestCase
{
    private const DATABASE = 'Iweb_Uninstall_Test';

    private const LOGIN = 'iweb_uninstall_web';

    private const PASSWORD = 'Uninstall-only_Password123!';

    private ?PDO $admin = null;

    /**
     * Provisions a disposable web database with the real bootstrap installer.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $configuration = config('database.connections.game');
        $configuration['database'] = 'master';
        config()->set('database.connections.application_admin', $configuration);
        $this->admin = $this->database()->connection('application_admin')->getPdo();
        $runtime = $configuration;
        $runtime['database'] = self::DATABASE;
        $runtime['username'] = self::LOGIN;
        $runtime['password'] = self::PASSWORD;
        config()->set('database.connections.sqlsrv', $runtime);
        config()->set('database.default', 'sqlsrv');
        $this->app->make(SqlServerApplicationDatabaseInstaller::class)->install(new ApplicationDatabaseInstallation(
            self::DATABASE, self::LOGIN, self::PASSWORD, 'muonline_web_application',
        ));
        $this->admin->exec('USE [master]');
        $this->admin->exec('CREATE TABLE [Iweb_Uninstall_Test].dbo.administrators (id int); CREATE TABLE [Iweb_Uninstall_Test].dbo.migrations (id int);');
    }

    /**
     * Removes only this test's resources, including an optional shared mapping in the fixture.
     */
    protected function tearDown(): void
    {
        if ($this->admin !== null) {
            $this->database()->purge('sqlsrv');
            $this->database()->purge('uninstall_test_active');
            $this->admin->exec("IF DB_ID('iweb_uninstall_test') IS NOT NULL AND DB_ID('iweb_uninstall_test') <> ISNULL(DB_ID('Iweb_Uninstall_Test'), -1) BEGIN ALTER DATABASE [iweb_uninstall_test] SET SINGLE_USER WITH ROLLBACK IMMEDIATE; DROP DATABASE [iweb_uninstall_test]; END;");
            $this->admin->exec('USE [MuOnline]; IF USER_ID(\'iweb_uninstall_web\') IS NOT NULL DROP USER [iweb_uninstall_web]; USE [master];');
            $this->admin->exec('USE [MuOnline]; IF USER_ID(\'iweb_uninstall_game_keep\') IS NOT NULL DROP USER [iweb_uninstall_game_keep]; USE [master];');
            $this->admin->exec("IF DB_ID('Iweb_Uninstall_Test') IS NOT NULL BEGIN ALTER DATABASE [Iweb_Uninstall_Test] SET SINGLE_USER WITH ROLLBACK IMMEDIATE; DROP DATABASE [Iweb_Uninstall_Test]; END;");
            $this->admin->exec("IF SUSER_ID('iweb_uninstall_web') IS NOT NULL DROP LOGIN [iweb_uninstall_web];");
            $this->admin->exec("IF SUSER_ID('iweb_uninstall_limited') IS NOT NULL DROP LOGIN [iweb_uninstall_limited];");
            $this->admin->exec("IF SUSER_ID('iweb_uninstall_game_keep') IS NOT NULL DROP LOGIN [iweb_uninstall_game_keep];");
        }

        parent::tearDown();
    }

    /**
     * Removes only the web pair and accepts another run after both resources are absent.
     */
    public function test_removal_is_idempotent_and_preserves_the_game_database(): void
    {
        $this->admin->exec("CREATE LOGIN [iweb_uninstall_game_keep] WITH PASSWORD = 'Game-keep_Password123!'; USE [MuOnline]; CREATE USER [iweb_uninstall_game_keep] FOR LOGIN [iweb_uninstall_game_keep]; USE [master];");
        config()->set('database.connections.game.username', 'iweb_uninstall_game_keep');

        $result = $this->remove();
        $second = $this->remove();

        self::assertTrue($result->ok);
        self::assertSame('removed', $result->databaseStatus);
        self::assertSame('removed', $result->loginStatus);
        self::assertTrue($second->ok);
        self::assertSame('already_absent', $second->databaseStatus);
        self::assertSame('already_absent', $second->loginStatus);
        self::assertNull($this->value("SELECT DB_ID('Iweb_Uninstall_Test')"));
        self::assertNull($this->value("SELECT SUSER_ID('iweb_uninstall_web')"));
        self::assertNotNull($this->value("SELECT OBJECT_ID('MuOnline.dbo.Character')"));
        self::assertNotNull($this->value("SELECT SUSER_ID('sa')"));
        self::assertNotNull($this->value("SELECT SUSER_ID('iweb_uninstall_game_keep')"));
        self::assertSame(1, (int) $this->value("SELECT COUNT(*) FROM MuOnline.sys.database_principals WHERE name = 'iweb_uninstall_game_keep'"));
    }

    /**
     * Refuses a web login mapped into the game fixture before dropping the web database.
     */
    public function test_shared_login_is_rejected_before_any_drop(): void
    {
        $this->admin->exec('USE [MuOnline]; CREATE USER [iweb_uninstall_web] FOR LOGIN [iweb_uninstall_web]; USE [master];');

        $result = $this->remove();

        self::assertFalse($result->ok);
        self::assertSame('shared_login', $result->code);
        $this->assertResourcesRetained();
    }

    /**
     * Refuses shared mappings in a distinct database whose name differs only by case.
     */
    public function test_case_distinct_database_mapping_is_not_skipped(): void
    {
        if ((int) $this->value("SELECT CASE WHEN N'A' = N'a' THEN 1 ELSE 0 END") === 1) {
            $this->markTestSkipped('Requires the case-sensitive SQL Server integration collation.');
        }

        $this->admin->exec("CREATE DATABASE [iweb_uninstall_test] ON PRIMARY (NAME = N'iweb_case_distinct_data', FILENAME = N'/var/opt/mssql/data/iweb_case_distinct_data.mdf') LOG ON (NAME = N'iweb_case_distinct_log', FILENAME = N'/var/opt/mssql/data/iweb_case_distinct_log.ldf')");
        $this->admin->exec('USE [iweb_uninstall_test]; CREATE USER [iweb_uninstall_web] FOR LOGIN [iweb_uninstall_web]; USE [master];');

        $result = $this->remove();

        self::assertFalse($result->ok);
        self::assertSame('shared_login', $result->code);
        $this->assertResourcesRetained();
        self::assertNotNull($this->value("SELECT DB_ID('iweb_uninstall_test')"));
    }

    /**
     * Refuses administrator-level runtime logins even when the configured names match.
     */
    public function test_privileged_login_is_rejected(): void
    {
        $this->admin->exec('ALTER SERVER ROLE sysadmin ADD MEMBER [iweb_uninstall_web]');

        $result = $this->remove();

        self::assertFalse($result->ok);
        self::assertSame('privileged_login', $result->code);
        $this->assertResourcesRetained();
    }

    /**
     * Refuses explicit server permissions outside the narrow dedicated runtime login contract.
     */
    public function test_server_permission_is_rejected(): void
    {
        $this->admin->exec('GRANT VIEW SERVER STATE TO [iweb_uninstall_web]');

        $result = $this->remove();

        self::assertFalse($result->ok);
        self::assertSame('privileged_login', $result->code);
        $this->assertResourcesRetained();
    }

    /**
     * Requires a fully privileged metadata inspector rather than guessing about shared users.
     */
    public function test_nonadministrator_credentials_cannot_uninstall(): void
    {
        $this->admin->exec("CREATE LOGIN [iweb_uninstall_limited] WITH PASSWORD = 'Limited-test_Password123!'");

        $result = $this->app->make(SqlServerApplicationDatabaseUninstaller::class)->uninstall(new ApplicationDatabaseRemoval(
            'sqlserver-test', 1433, self::DATABASE, self::LOGIN, 'iweb_uninstall_limited', 'Limited-test_Password123!',
        ));

        self::assertFalse($result->ok);
        self::assertSame('sysadmin_required', $result->code);
        $this->assertResourcesRetained();
    }

    /**
     * Protects a misconfigured game-like database despite an application-role name.
     */
    public function test_game_table_signature_prevents_database_removal(): void
    {
        $this->admin->exec('CREATE TABLE [Iweb_Uninstall_Test].dbo.Character (Name varchar(10))');

        $result = $this->remove();

        self::assertFalse($result->ok);
        self::assertSame('unverified_web_database', $result->code);
        $this->assertResourcesRetained();
    }

    /**
     * Rejects another login mapped to the target database rather than removing shared data.
     */
    public function test_database_with_another_user_is_rejected(): void
    {
        $this->admin->exec('USE [Iweb_Uninstall_Test]; CREATE USER [other_application] WITHOUT LOGIN; USE [master];');

        $result = $this->remove();

        self::assertFalse($result->ok);
        self::assertSame('shared_database', $result->code);
        $this->assertResourcesRetained();
    }

    /**
     * Completes a retry where the database was already removed but the login remains.
     */
    public function test_retry_cleans_up_a_leftover_login(): void
    {
        $this->admin->exec('DROP DATABASE [Iweb_Uninstall_Test]');

        $result = $this->remove();

        self::assertTrue($result->ok);
        self::assertSame('already_absent', $result->databaseStatus);
        self::assertSame('removed', $result->loginStatus);
    }

    /**
     * Completes a retry when the login was already removed and the verified web schema remains.
     */
    public function test_retry_cleans_up_the_database_after_login_was_removed(): void
    {
        $this->admin->exec('DROP LOGIN [iweb_uninstall_web]');

        $result = $this->remove();

        self::assertTrue($result->ok);
        self::assertSame('removed', $result->databaseStatus);
        self::assertSame('already_absent', $result->loginStatus);
    }

    /**
     * Reports a partial drop when an active login session blocks deletion and completes on retry.
     */
    public function test_partial_login_failure_can_be_retried(): void
    {
        $configuration = config('database.connections.sqlsrv');
        $configuration['database'] = 'master';
        $this->database()->connectUsing('uninstall_test_active', $configuration, force: true)->getPdo();

        $partial = $this->remove();

        self::assertFalse($partial->ok);
        self::assertSame('removed', $partial->databaseStatus);
        self::assertSame('unknown', $partial->loginStatus);
        self::assertTrue($partial->retryable);
        $this->database()->purge('uninstall_test_active');

        $completed = $this->remove();

        self::assertTrue($completed->ok);
        self::assertSame('already_absent', $completed->databaseStatus);
        self::assertSame('removed', $completed->loginStatus);
    }

    /**
     * Calls the real service with only disposable SQL coordinates and administrator credentials.
     */
    private function remove(): ApplicationDatabaseRemovalResult
    {
        return $this->app->make(SqlServerApplicationDatabaseUninstaller::class)->uninstall(new ApplicationDatabaseRemoval(
            'sqlserver-test', 1433, self::DATABASE, self::LOGIN, 'sa', 'Integration-only_Password123',
        ));
    }

    /**
     * Confirms both uninstall targets are intact after a refused preflight.
     */
    private function assertResourcesRetained(): void
    {
        self::assertNotNull($this->value("SELECT DB_ID('Iweb_Uninstall_Test')"));
        self::assertNotNull($this->value("SELECT SUSER_ID('iweb_uninstall_web')"));
    }

    /**
     * Reads one assertion value without going through the configured runtime login.
     */
    private function value(string $query): mixed
    {
        $value = $this->admin->query($query)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * Resolves the integration fixture's isolated connection manager.
     */
    private function database(): DatabaseManager
    {
        return $this->app->make(DatabaseManager::class);
    }
}
