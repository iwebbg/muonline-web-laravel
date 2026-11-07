<?php

namespace Tests\Feature;

use App\Installation\Contracts\GameDatabaseInstaller;
use App\Installation\GameDatabaseInstallation;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifies the guarded first-start game database access installation command.
 */
final class InstallGameDatabaseCommandTest extends TestCase
{
    /**
     * Supplies a safe restricted game database configuration to each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.database_bootstrap.enabled', true);
        config()->set('game.database_bootstrap.role', 'muonline_web_application');
        config()->set('game.data_source', 'database');
        config()->set('database.connections.game.driver', 'sqlsrv');
        config()->set('database.connections.game.database', 'MuOnline_1_0M');
        config()->set('database.connections.game.username', 'muonline_1_0m_game_app');
        config()->set('database.connections.game.password', 'Strong-game-password-123!');
        config()->set('database.connections.game_admin.username', 'sa');
        config()->set('database.connections.game_admin.password', 'Temporary-admin-password-123!');
        config()->set('database.connections.sqlsrv.username', 'muonline_web_app');
    }

    /**
     * Provisions the restricted principal through the installer boundary.
     */
    public function test_valid_configuration_installs_game_database_access(): void
    {
        $installer = new FakeGameDatabaseInstaller;
        $this->app->instance(GameDatabaseInstaller::class, $installer);

        $this->artisan('game:database:install')
            ->expectsOutputToContain('Restricted game database access is ready in [MuOnline_1_0M].')
            ->expectsOutputToContain('APP_DB_BOOTSTRAP=false')
            ->assertSuccessful();

        $this->assertInstanceOf(GameDatabaseInstallation::class, $installer->installation);
        $this->assertSame('MuOnline_1_0M', $installer->installation->databaseName);
        $this->assertSame('muonline_1_0m_game_app', $installer->installation->loginName);
        $this->assertSame('Strong-game-password-123!', $installer->installation->loginPassword);
        $this->assertSame('muonline_web_application', $installer->installation->roleName);
    }

    /**
     * Rejects unsafe configuration before opening the administrator connection.
     *
     * @param  array<string, mixed>  $configuration
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function test_unsafe_configuration_is_rejected(
        array $configuration,
        string $expectedMessage,
    ): void {
        foreach ($configuration as $key => $value) {
            config()->set($key, $value);
        }

        $installer = new FakeGameDatabaseInstaller;
        $this->app->instance(GameDatabaseInstaller::class, $installer);

        $this->artisan('game:database:install')
            ->expectsOutputToContain($expectedMessage)
            ->assertFailed();

        $this->assertNull($installer->installation);
    }

    /**
     * Supplies each high-risk configuration that must fail before provisioning.
     *
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'bootstrap disabled' => [
            ['game.database_bootstrap.enabled' => false],
            'Set APP_DB_BOOTSTRAP=true',
        ];

        yield 'fixture data source' => [
            ['game.data_source' => 'fixture'],
            'Set GAME_DATA_SOURCE=database',
        ];

        yield 'non SQL Server connection' => [
            ['database.connections.game.driver' => 'sqlite'],
            'requires GAME_DB_CONNECTION=sqlsrv',
        ];

        yield 'system database' => [
            ['database.connections.game.database' => 'master'],
            'GAME_DB_MUONLINE must identify an existing game database',
        ];

        yield 'administrator runtime login' => [
            ['database.connections.game.username' => 'sa'],
            'GAME_DB_USERNAME must be a dedicated non-administrator login',
        ];

        yield 'missing bootstrap credentials' => [
            ['database.connections.game_admin.password' => ''],
            'GAME_DB_ADMIN_* or APP_DB_ADMIN_* credentials are required',
        ];

        yield 'same bootstrap and runtime login' => [
            ['database.connections.game_admin.username' => 'MUONLINE_1_0M_GAME_APP'],
            'runtime GAME_DB_USERNAME must differ',
        ];

        yield 'shared application and game login' => [
            ['database.connections.sqlsrv.username' => 'MUONLINE_1_0M_GAME_APP'],
            'GAME_DB_USERNAME must differ from the application DB_USERNAME',
        ];

        yield 'unsafe identifier' => [
            ['database.connections.game.database' => 'MuOnline];DROP'],
            'may contain only letters, numbers and underscores',
        ];

        yield 'short runtime password' => [
            ['database.connections.game.password' => 'Short-1!'],
            'GAME_DB_PASSWORD must contain at least 16 characters',
        ];

        yield 'password without uppercase character' => [
            ['database.connections.game.password' => 'lowercase-password-123!'],
            'uppercase, lowercase, a number and a symbol',
        ];

        yield 'password without symbol' => [
            ['database.connections.game.password' => 'PasswordWithoutSymbol123'],
            'uppercase, lowercase, a number and a symbol',
        ];
    }

    /**
     * Hides provider exceptions so credentials cannot leak through console output.
     */
    public function test_installer_failure_returns_a_generic_error_without_secret_details(): void
    {
        $installer = new FakeGameDatabaseInstaller(
            failure: new RuntimeException('Temporary-admin-password-123!'),
        );
        $this->app->instance(GameDatabaseInstaller::class, $installer);

        $this->artisan('game:database:install')
            ->expectsOutputToContain('Game database access installation failed')
            ->doesntExpectOutputToContain('Temporary-admin-password-123!')
            ->assertFailed();

        $this->assertNull($installer->installation);
    }
}

/**
 * Captures game database installation requests without connecting to SQL Server.
 */
final class FakeGameDatabaseInstaller implements GameDatabaseInstaller
{
    public ?GameDatabaseInstallation $installation = null;

    /**
     * Creates a controllable installer fake for command feature tests.
     */
    public function __construct(
        private readonly ?RuntimeException $failure = null,
    ) {}

    /**
     * Captures the request or raises the configured boundary failure.
     */
    public function install(GameDatabaseInstallation $installation): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->installation = $installation;
    }
}
