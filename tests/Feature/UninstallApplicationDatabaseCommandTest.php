<?php

namespace Tests\Feature;

use App\Installation\ApplicationDatabaseRemoval;
use App\Installation\ApplicationDatabaseRemovalResult;
use App\Installation\Contracts\ApplicationDatabaseUninstaller;
use Illuminate\Contracts\Console\Kernel;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Verifies the uninstall CLI confirmation, private-input and redacted-output contract.
 */
final class UninstallApplicationDatabaseCommandTest extends TestCase
{
    /**
     * Passes exact confirmed coordinates and credentials without serializing the password.
     */
    public function test_confirmed_stdin_request_returns_safe_json(): void
    {
        $this->mock(ApplicationDatabaseUninstaller::class)->shouldReceive('uninstall')->once()
            ->withArgs(function (ApplicationDatabaseRemoval $request): bool {
                return $request->host === 'sql.example.test'
                    && $request->port === 1433
                    && $request->databaseName === 'MuOnline_Web'
                    && $request->loginName === 'muonline_web_app'
                    && $request->adminUsername === 'sa'
                    && $request->adminPassword === 'TemporarySecret!123';
            })
            ->andReturn(new ApplicationDatabaseRemovalResult(true, 'uninstalled', 'removed', 'removed'));

        [$status, $result] = $this->runCommand(json_encode($this->payload(), JSON_THROW_ON_ERROR));

        $this->assertSame(0, $status);
        $this->assertSame(['ok' => true, 'code' => 'uninstalled', 'database' => 'removed', 'login' => 'removed', 'retryable' => false], $result);
    }

    /**
     * Refuses execution without the explicit destructive command flag.
     */
    public function test_force_is_required_before_reading_credentials(): void
    {
        $this->mock(ApplicationDatabaseUninstaller::class)->shouldNotReceive('uninstall');

        [$status, $result] = $this->runCommand(json_encode($this->payload()), force: false);

        $this->assertSame(1, $status);
        $this->assertSame('confirmation_required', $result['code']);
    }

    /**
     * Rejects malformed JSON and oversized input before invoking the SQL boundary.
     */
    #[DataProvider('invalidJson')]
    public function test_invalid_json_is_rejected(string $input): void
    {
        $this->mock(ApplicationDatabaseUninstaller::class)->shouldNotReceive('uninstall');

        [$status, $result] = $this->runCommand($input);

        $this->assertSame(1, $status);
        $this->assertSame('invalid_input', $result['code']);
    }

    /**
     * Provides invalid wire formats rather than implementation-specific parser assertions.
     *
     * @return array<string, array{string}>
     */
    public static function invalidJson(): array
    {
        return ['broken' => ['{'], 'list' => ['[]'], 'null' => ['null'], 'oversized' => [str_repeat('x', 16385)]];
    }

    /**
     * Requires strict confirmation, safe targets and credentials from the supplied JSON only.
     *
     * @param  array<string, mixed>  $changes
     */
    #[DataProvider('invalidFields')]
    public function test_invalid_fields_never_reach_sql(array $changes): void
    {
        $this->mock(ApplicationDatabaseUninstaller::class)->shouldNotReceive('uninstall');

        [$status, $result] = $this->runCommand(json_encode([...$this->payload(), ...$changes]));

        $this->assertSame(1, $status);
        $this->assertSame('invalid_input', $result['code']);
    }

    /**
     * Covers confirmation bypasses, target injection and missing private credentials.
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidFields(): array
    {
        return [
            'not confirmed' => [['confirmed' => false]],
            'string confirmation' => [['confirmed' => 'true']],
            'numeric confirmation' => [['confirmed' => 1]],
            'bad host' => [['host' => 'server;Database=MuOnline']],
            'bad port' => [['port' => 0]],
            'bad database' => [['database' => 'master];DROP DATABASE x']],
            'bad login' => [['login' => 'user;DROP LOGIN sa']],
            'missing username' => [['admin_username' => '']],
            'missing password' => [['admin_password' => '']],
            'unknown option' => [['drop_game_database' => true]],
        ];
    }

    /**
     * Returns only a generic error when the SQL boundary throws a secret-bearing exception.
     */
    public function test_exception_details_are_not_exposed(): void
    {
        $this->mock(ApplicationDatabaseUninstaller::class)->shouldReceive('uninstall')->once()
            ->andThrow(new RuntimeException('Password=TemporarySecret!123; sensitive SQL'));

        [$status, $result] = $this->runCommand(json_encode($this->payload()));

        $this->assertSame(1, $status);
        $this->assertSame('uninstall_failed', $result['code']);
        $this->assertStringNotContainsString('TemporarySecret', json_encode($result));
    }

    /**
     * Propagates resource-level partial progress for retry without inventing rollback.
     */
    public function test_partial_completion_remains_a_failure_with_progress(): void
    {
        $this->mock(ApplicationDatabaseUninstaller::class)->shouldReceive('uninstall')->once()
            ->andReturn(new ApplicationDatabaseRemovalResult(false, 'sql_operation_failed', 'removed', 'unknown', true));

        [$status, $result] = $this->runCommand(json_encode($this->payload()));

        $this->assertSame(1, $status);
        $this->assertSame('removed', $result['database']);
        $this->assertSame('unknown', $result['login']);
        $this->assertTrue($result['retryable']);
    }

    /**
     * Refuses unsafe configuration before any SQL connection can be established.
     *
     * @param  array<string, mixed>  $configuration
     */
    #[DataProvider('unsafeTargets')]
    public function test_unsafe_configured_targets_are_refused(array $configuration, string $expectedCode): void
    {
        config()->set('database.default', 'sqlsrv');
        config()->set('database.connections.sqlsrv', ['driver' => 'sqlsrv', 'host' => 'sql.example.test', 'port' => 1433, 'database' => 'MuOnline_Web', 'username' => 'muonline_web_app']);
        config()->set($configuration);
        $request = $this->payload();
        $request['host'] = config('database.connections.sqlsrv.host');
        $request['database'] = config('database.connections.sqlsrv.database');
        $request['login'] = config('database.connections.sqlsrv.username');

        [$status, $result] = $this->runCommand(json_encode($request));

        $this->assertSame(1, $status);
        $this->assertSame($expectedCode, $result['code']);
        $this->assertSame('not_checked', $result['database']);
    }

    /**
     * Covers configured system/game resources, shared credentials and hidden DSN overrides.
     *
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function unsafeTargets(): array
    {
        return [
            'system' => [['database.connections.sqlsrv.database' => 'master'], 'protected_database'],
            'game' => [['database.connections.sqlsrv.database' => 'MuOnline'], 'protected_database'],
            'battle core' => [['database.connections.battle_core.database' => 'BattleCore_S21', 'database.connections.sqlsrv.database' => 'BattleCore_S21'], 'protected_database'],
            'custom game' => [['database.connections.game.database' => 'CustomGame', 'database.connections.sqlsrv.database' => 'CustomGame'], 'protected_database'],
            'sa' => [['database.connections.sqlsrv.username' => 'sa'], 'protected_login'],
            'shared game login' => [['database.connections.game.username' => 'muonline_web_app'], 'protected_login'],
            'dsn override' => [['database.connections.sqlsrv.url' => 'sqlsrv://different'], 'target_mismatch'],
            'wrong port' => [['database.connections.sqlsrv.port' => 1434], 'target_mismatch'],
            'not SQL Server' => [['database.default' => 'sqlite'], 'target_mismatch'],
        ];
    }

    /**
     * Provides one explicit uninstall target without reading bootstrap credentials from env.
     *
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return ['host' => 'sql.example.test', 'port' => 1433, 'database' => 'MuOnline_Web', 'login' => 'muonline_web_app', 'admin_username' => 'sa', 'admin_password' => 'TemporarySecret!123', 'confirmed' => true];
    }

    /**
     * Executes the command with a closed private input stream and parses raw JSON output.
     *
     * @return array{int, array<string, mixed>}
     */
    private function runCommand(string $payload, bool $force = true): array
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $payload);
        rewind($stream);
        $arguments = ['command' => 'app:database:uninstall', '--no-interaction' => true];

        if ($force) {
            $arguments['--force'] = true;
        }

        $input = new ArrayInput($arguments);
        $input->setStream($stream);
        $input->setInteractive(false);
        $output = new BufferedOutput;

        try {
            $status = $this->app->make(Kernel::class)->handle($input, $output);

            return [$status, json_decode(trim($output->fetch()), true, flags: JSON_THROW_ON_ERROR)];
        } finally {
            fclose($stream);
        }
    }
}
