<?php

namespace App\Console\Commands;

use App\Installation\ApplicationDatabaseInstallation;
use App\Installation\Contracts\ApplicationDatabaseInstaller;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:database:install')]
/**
 * Installs the isolated Laravel database without using administrator credentials at runtime.
 */
#[Description('Create the SQL Server application database and its restricted runtime login.')]
final class InstallApplicationDatabase extends Command
{
    /**
     * Validates the bootstrap boundary and provisions the configured application database.
     */
    public function handle(ApplicationDatabaseInstaller $installer): int
    {
        if (! (bool) config('application_database.bootstrap.enabled')) {
            $this->components->error('Set APP_DB_BOOTSTRAP=true only for the first database installation.');

            return self::FAILURE;
        }

        if (config('database.default') !== 'sqlsrv') {
            $this->components->error('Automatic application database installation requires DB_CONNECTION=sqlsrv.');

            return self::FAILURE;
        }

        $databaseName = $this->configuredString('database.connections.sqlsrv.database');
        $loginName = $this->configuredString('database.connections.sqlsrv.username');
        $loginPassword = $this->configuredString('database.connections.sqlsrv.password', trim: false);
        $adminLoginName = $this->configuredString('database.connections.application_admin.username');
        $adminPassword = $this->configuredString(
            'database.connections.application_admin.password',
            trim: false,
        );
        $roleName = $this->configuredString('application_database.application_role');
        $gameDatabaseName = $this->configuredString('database.connections.game.database');

        if (in_array($databaseName, ['master', 'model', 'msdb', 'tempdb'], true)) {
            $this->components->error('DB_DATABASE must be an isolated application database.');

            return self::FAILURE;
        }

        if (strcasecmp($databaseName, $gameDatabaseName) === 0) {
            $this->components->error('DB_DATABASE must not be the MuOnline game database.');

            return self::FAILURE;
        }

        if ($loginName === '' || strcasecmp($loginName, 'sa') === 0) {
            $this->components->error('DB_USERNAME must be a dedicated non-administrator login.');

            return self::FAILURE;
        }

        if ($adminLoginName === '' || $adminPassword === '') {
            $this->components->error('APP_DB_ADMIN_USERNAME and APP_DB_ADMIN_PASSWORD are required for first start.');

            return self::FAILURE;
        }

        if (strcasecmp($loginName, $adminLoginName) === 0) {
            $this->components->error('The runtime DB_USERNAME must differ from APP_DB_ADMIN_USERNAME.');

            return self::FAILURE;
        }

        if (! $this->isSafeIdentifier($databaseName)
            || ! $this->isSafeIdentifier($loginName)
            || ! $this->isSafeIdentifier($roleName)) {
            $this->components->error('Database, login and role names may contain only letters, numbers and underscores.');

            return self::FAILURE;
        }

        if (mb_strlen($loginPassword) < 16 || preg_match('/[\x00-\x1F\x7F]/', $loginPassword) === 1) {
            $this->components->error('DB_PASSWORD must contain at least 16 characters and no control characters.');

            return self::FAILURE;
        }

        $installation = new ApplicationDatabaseInstallation(
            databaseName: $databaseName,
            loginName: $loginName,
            loginPassword: $loginPassword,
            roleName: $roleName,
        );

        try {
            $installer->install($installation);
        } catch (Throwable) {
            $this->components->error('Application database installation failed. Check the SQL Server connection and bootstrap credentials.');

            return self::FAILURE;
        }

        $this->components->info("Application database [{$databaseName}] is ready.");
        $this->line('Remove APP_DB_ADMIN_PASSWORD and set APP_DB_BOOTSTRAP=false after this first successful start.');

        return self::SUCCESS;
    }

    /**
     * Reads one scalar configuration value without consulting the environment directly.
     */
    private function configuredString(string $key, bool $trim = true): string
    {
        $value = config($key);

        if (! is_scalar($value)) {
            return '';
        }

        $configuredValue = (string) $value;

        return $trim ? trim($configuredValue) : $configuredValue;
    }

    /**
     * Restricts provisioned identifiers to a predictable SQL Server allow-list.
     */
    private function isSafeIdentifier(string $identifier): bool
    {
        return preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,127}\z/', $identifier) === 1;
    }
}
