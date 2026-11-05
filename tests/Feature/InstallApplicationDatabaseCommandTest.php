<?php

namespace Tests\Feature;

use App\Installation\ApplicationDatabaseInstallation;
use App\Installation\Contracts\ApplicationDatabaseInstaller;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifies the guarded first-start application database installation command.
 */
final class InstallApplicationDatabaseCommandTest extends TestCase
{
    /**
     * Supplies a safe isolated application database configuration to each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('application_database.bootstrap.enabled', true);
        config()->set('application_database.application_role', 'muonline_web_application');
        config()->set('database.default', 'sqlsrv');
        config()->set('database.connections.sqlsrv.database', 'MuOnline_Web');
        config()->set('database.connections.sqlsrv.username', 'muonline_web_app');
        config()->set('database.connections.sqlsrv.password', 'A-long-runtime-password-123!');
        config()->set('database.connections.application_admin.username', 'sa');
        config()->set('database.connections.application_admin.password', 'Temporary-admin-password-123!');
        config()->set('database.connections.game.database', 'MuOnline');
    }

    /**
     * Provisions the isolated database through the installer boundary.
     */
    public function test_valid_configuration_installs_the_application_database(): void
    {
        $installer = new FakeApplicationDatabaseInstaller;
        $this->app->instance(ApplicationDatabaseInstaller::class, $installer);

        $this->artisan('app:database:install')
            ->expectsOutputToContain('Application database [MuOnline_Web] is ready.')
            ->expectsOutputToContain('APP_DB_BOOTSTRAP=false')
            ->assertSuccessful();

        $this->assertInstanceOf(ApplicationDatabaseInstallation::class, $installer->installation);
        $this->assertSame('MuOnline_Web', $installer->installation->databaseName);
        $this->assertSame('muonline_web_app', $installer->installation->loginName);
        $this->assertSame('A-long-runtime-password-123!', $installer->installation->loginPassword);
        $this->assertSame('muonline_web_application', $installer->installation->roleName);
    }

    /**
     * Refuses to install Laravel tables into the legacy game database.
     */
    public function test_game_database_cannot_be_used_as_the_application_database(): void
    {
        config()->set('database.connections.sqlsrv.database', 'MuOnline');
        $installer = new FakeApplicationDatabaseInstaller;
        $this->app->instance(ApplicationDatabaseInstaller::class, $installer);

        $this->artisan('app:database:install')
            ->expectsOutputToContain('DB_DATABASE must not be the MuOnline game database')
            ->assertFailed();

        $this->assertNull($installer->installation);
    }

    /**
     * Refuses first start without temporary administrator credentials.
     */
    public function test_bootstrap_requires_temporary_administrator_credentials(): void
    {
        config()->set('database.connections.application_admin.password', '');
        $installer = new FakeApplicationDatabaseInstaller;
        $this->app->instance(ApplicationDatabaseInstaller::class, $installer);

        $this->artisan('app:database:install')
            ->expectsOutputToContain('APP_DB_ADMIN_USERNAME and APP_DB_ADMIN_PASSWORD are required')
            ->assertFailed();

        $this->assertNull($installer->installation);
    }

    /**
     * Rejects unsafe or privileged runtime configurations before opening SQL Server.
     *
     * @param  array<string, mixed>  $configuration
     */
    #[DataProvider('invalidConfigurationProvider')]
    public function test_unsafe_runtime_configuration_is_rejected(
        array $configuration,
        string $expectedMessage,
    ): void {
        foreach ($configuration as $key => $value) {
            config()->set($key, $value);
        }

        $installer = new FakeApplicationDatabaseInstaller;
        $this->app->instance(ApplicationDatabaseInstaller::class, $installer);

        $this->artisan('app:database:install')
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
            ['application_database.bootstrap.enabled' => false],
            'Set APP_DB_BOOTSTRAP=true',
        ];

        yield 'non SQL Server connection' => [
            ['database.default' => 'sqlite'],
            'requires DB_CONNECTION=sqlsrv',
        ];

        yield 'system database' => [
            ['database.connections.sqlsrv.database' => 'master'],
            'DB_DATABASE must be an isolated application database',
        ];

        yield 'administrator runtime login' => [
            ['database.connections.sqlsrv.username' => 'sa'],
            'DB_USERNAME must be a dedicated non-administrator login',
        ];

        yield 'same bootstrap and runtime login' => [
            ['database.connections.application_admin.username' => 'MUONLINE_WEB_APP'],
            'runtime DB_USERNAME must differ',
        ];

        yield 'unsafe identifier' => [
            ['database.connections.sqlsrv.database' => 'MuOnline_Web;DROP'],
            'may contain only letters, numbers and underscores',
        ];

        yield 'weak runtime password' => [
            ['database.connections.sqlsrv.password' => 'short'],
            'DB_PASSWORD must contain at least 16 characters',
        ];
    }

    /**
     * Hides provider exceptions so credentials cannot leak through console output.
     */
    public function test_installer_failure_returns_a_generic_error_without_secret_details(): void
    {
        $installer = new FakeApplicationDatabaseInstaller(
            failure: new RuntimeException('Temporary-admin-password-123!'),
        );
        $this->app->instance(ApplicationDatabaseInstaller::class, $installer);

        $this->artisan('app:database:install')
            ->expectsOutputToContain('Application database installation failed')
            ->doesntExpectOutputToContain('Temporary-admin-password-123!')
            ->assertFailed();
    }
}

/**
 * Captures database installation requests without connecting to SQL Server.
 */
final class FakeApplicationDatabaseInstaller implements ApplicationDatabaseInstaller
{
    public ?ApplicationDatabaseInstallation $installation = null;

    /**
     * Creates a controllable installer fake for command feature tests.
     */
    public function __construct(
        private readonly ?RuntimeException $failure = null,
    ) {}

    /**
     * Captures the request or raises the configured boundary failure.
     */
    public function install(ApplicationDatabaseInstallation $installation): void
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->installation = $installation;
    }
}
