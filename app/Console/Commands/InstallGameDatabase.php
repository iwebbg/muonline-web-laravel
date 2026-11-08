<?php

namespace App\Console\Commands;

use App\Installation\Contracts\GameDatabaseInstaller;
use App\Installation\GameDatabaseInstallation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('game:database:install')]
/**
 * Installs the restricted SQL Server identity used by game adapters.
 */
#[Description('Create the restricted game database login, user, role and adapter permissions.')]
final class InstallGameDatabase extends Command
{
    /**
     * Validates the bootstrap boundary and provisions access to the existing game database.
     */
    public function handle(GameDatabaseInstaller $installer): int
    {
        if (! (bool) config('game.database_bootstrap.enabled')) {
            $this->components->error('Set APP_DB_BOOTSTRAP=true only for the first database installation.');

            return self::FAILURE;
        }

        if (config('game.data_source') !== 'database') {
            $this->components->error('Set GAME_DATA_SOURCE=database before installing game database access.');

            return self::FAILURE;
        }

        if (config('database.connections.game.driver') !== 'sqlsrv') {
            $this->components->error('Automatic game database access installation requires GAME_DB_CONNECTION=sqlsrv.');

            return self::FAILURE;
        }

        $databaseName = $this->configuredString('database.connections.game.database');
        $loginName = $this->configuredString('database.connections.game.username');
        $loginPassword = $this->configuredString('database.connections.game.password', trim: false);
        $adminLoginName = $this->configuredString('database.connections.game_admin.username');
        $adminPassword = $this->configuredString(
            'database.connections.game_admin.password',
            trim: false,
        );
        $applicationLoginName = $this->configuredString('database.connections.sqlsrv.username');
        $roleName = $this->configuredString('game.database_bootstrap.role');

        if (in_array(mb_strtolower($databaseName), ['master', 'model', 'msdb', 'tempdb'], true)) {
            $this->components->error('GAME_DB_MUONLINE must identify an existing game database.');

            return self::FAILURE;
        }

        if ($loginName === '' || strcasecmp($loginName, 'sa') === 0) {
            $this->components->error('GAME_DB_USERNAME must be a dedicated non-administrator login.');

            return self::FAILURE;
        }

        if ($adminLoginName === '' || $adminPassword === '') {
            $this->components->error('GAME_DB_ADMIN_* or APP_DB_ADMIN_* credentials are required for first start.');

            return self::FAILURE;
        }

        if (strcasecmp($loginName, $adminLoginName) === 0) {
            $this->components->error('The runtime GAME_DB_USERNAME must differ from the game administrator login.');

            return self::FAILURE;
        }

        if ($applicationLoginName !== '' && strcasecmp($loginName, $applicationLoginName) === 0) {
            $this->components->error('GAME_DB_USERNAME must differ from the application DB_USERNAME.');

            return self::FAILURE;
        }

        if (! $this->isSafeIdentifier($databaseName)
            || ! $this->isSafeIdentifier($loginName)
            || ! $this->isSafeIdentifier($roleName)) {
            $this->components->error('Database, login and role names may contain only letters, numbers and underscores.');

            return self::FAILURE;
        }

        if (! $this->isStrongPassword($loginPassword)) {
            $this->components->error(
                'GAME_DB_PASSWORD must contain at least 16 characters, uppercase, lowercase, a number and a symbol.',
            );

            return self::FAILURE;
        }

        $installation = new GameDatabaseInstallation(
            databaseName: $databaseName,
            loginName: $loginName,
            loginPassword: $loginPassword,
            roleName: $roleName,
        );

        try {
            $installer->install($installation);
        } catch (Throwable) {
            $this->components->error('Game database access installation failed. Check the database and temporary administrator credentials.');

            return self::FAILURE;
        }

        $this->components->info("Restricted game database access is ready in [{$databaseName}].");
        $this->line('Remove GAME_DB_ADMIN_PASSWORD and APP_DB_ADMIN_PASSWORD, then set APP_DB_BOOTSTRAP=false.');

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

    /**
     * Requires a SQL Server policy-compatible runtime password without control characters.
     */
    private function isStrongPassword(string $password): bool
    {
        return mb_strlen($password) >= 16
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^A-Za-z0-9]/', $password) === 1
            && preg_match('/[\x00-\x1F\x7F]/', $password) !== 1;
    }
}
