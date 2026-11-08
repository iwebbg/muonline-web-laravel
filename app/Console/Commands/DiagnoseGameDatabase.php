<?php

namespace App\Console\Commands;

use App\Game\Versions\Contracts\GameDatabaseProfile;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Throwable;

#[Signature('game:doctor')]
/**
 * Performs read-only connection, schema and least-privilege diagnostics.
 */
#[Description('Verify the active game database profile, connection and SQL permissions.')]
final class DiagnoseGameDatabase extends Command
{
    /**
     * Lists object capabilities that the web identity must never inherit.
     *
     * @var array<string, list<string>>
     */
    private const FORBIDDEN_OBJECT_PERMISSIONS = [
        'dbo.MEMB_INFO' => ['DELETE', 'CONTROL', 'ALTER', 'TAKE OWNERSHIP'],
        'dbo.MEMB_STAT' => ['INSERT', 'UPDATE', 'DELETE', 'CONTROL', 'ALTER', 'TAKE OWNERSHIP'],
        'dbo.Character' => ['INSERT', 'DELETE', 'CONTROL', 'ALTER', 'TAKE OWNERSHIP'],
        'dbo.warehouse' => ['INSERT', 'DELETE', 'CONTROL', 'ALTER', 'TAKE OWNERSHIP'],
        'dbo.Guild' => ['INSERT', 'UPDATE', 'DELETE', 'CONTROL', 'ALTER', 'TAKE OWNERSHIP'],
        'dbo.GuildMember' => ['INSERT', 'UPDATE', 'DELETE', 'CONTROL', 'ALTER', 'TAKE OWNERSHIP'],
        'dbo.VI_CURR_INFO' => ['UPDATE', 'DELETE', 'CONTROL', 'ALTER', 'TAKE OWNERSHIP'],
        'dbo.GameServerInfo' => ['INSERT', 'UPDATE', 'DELETE', 'CONTROL', 'ALTER', 'TAKE OWNERSHIP'],
        'dbo.WZ_GetItemSerial' => ['CONTROL', 'ALTER', 'TAKE OWNERSHIP'],
    ];

    /**
     * Lists elevated server and database permissions forbidden to the web identity.
     *
     * @var array<string, list<string>>
     */
    private const FORBIDDEN_SCOPE_PERMISSIONS = [
        'SERVER' => ['CONTROL SERVER', 'ALTER ANY LOGIN'],
        'DATABASE' => [
            'CONTROL',
            'ALTER',
            'TAKE OWNERSHIP',
            'BACKUP DATABASE',
            'CREATE TABLE',
            'CREATE PROCEDURE',
            'CREATE VIEW',
            'CREATE FUNCTION',
        ],
    ];

    /**
     * Executes read-only diagnostics and fails on unsafe production boundaries.
     */
    public function handle(
        DatabaseManager $database,
        GameDatabaseProfile $profile,
    ): int {
        try {
            $connection = $database->connection('game');
            $connection->selectOne('SELECT CURRENT_TIMESTAMP AS server_time');
        } catch (Throwable) {
            $this->components->error('Game database connection failed.');

            return self::FAILURE;
        }

        $this->components->info('Game database connection is available.');
        $schemaIsValid = $this->verifySchema($connection, $profile);
        $permissionsAreSafe = $this->verifyPermissions($connection);
        $scopePermissionsAreSafe = $this->verifyScopePermissions($connection);
        $itemSerialSourceIsAvailable = $this->verifyItemSerialSource($connection);
        $objectPermissionsAreAvailable = $this->verifyObjectPermissions($connection, $profile);
        $configurationIsSafe = $this->verifySafetyConfiguration();

        if (
            ! $schemaIsValid
            || ! $permissionsAreSafe
            || ! $scopePermissionsAreSafe
            || ! $itemSerialSourceIsAvailable
            || ! $objectPermissionsAreAvailable
            || ! $configurationIsSafe
        ) {
            $this->components->error('Game database diagnostics failed.');

            return self::FAILURE;
        }

        $this->components->info('Game database diagnostics passed.');

        return self::SUCCESS;
    }

    /**
     * Rejects broad server and database capabilities inherited through custom roles.
     */
    private function verifyScopePermissions(Connection $connection): bool
    {
        if ($connection->getDriverName() !== 'sqlsrv') {
            $this->components->warn('Elevated permission verification skipped for a non-SQL Server test connection.');

            return true;
        }

        $valid = true;

        foreach (self::FORBIDDEN_SCOPE_PERMISSIONS as $scope => $permissions) {
            foreach ($permissions as $permission) {
                $securable = $scope === 'DATABASE' ? $connection->getDatabaseName() : null;
                $result = $connection->selectOne(
                    'SELECT HAS_PERMS_BY_NAME(?, ?, ?) AS has_permission',
                    [$securable, $scope, $permission],
                );

                if ((int) ($result->has_permission ?? 0) === 1) {
                    $this->components->error("Unsafe SQL permission: {$permission} on {$scope}");
                    $valid = false;
                }
            }
        }

        if ($valid) {
            $this->components->info('SQL identity has no forbidden elevated permissions.');
        }

        return $valid;
    }

    /**
     * Confirms the SQL identity has the exact read and write capabilities used by adapters.
     */
    private function verifyObjectPermissions(
        Connection $connection,
        GameDatabaseProfile $profile,
    ): bool {
        if ($connection->getDriverName() !== 'sqlsrv') {
            $this->components->warn('Object permission verification skipped for a non-SQL Server test connection.');

            return true;
        }

        $valid = true;

        foreach ($profile->databaseObjectPermissions() as $object => $permissions) {
            foreach ($permissions as $permission) {
                $result = $connection->selectOne(
                    'SELECT HAS_PERMS_BY_NAME(?, ?, ?) AS has_permission',
                    [$object, 'OBJECT', $permission],
                );

                if ((int) ($result->has_permission ?? 0) !== 1) {
                    $this->components->error("Missing SQL permission: {$permission} on {$object}");
                    $valid = false;
                }
            }
        }

        foreach (self::FORBIDDEN_OBJECT_PERMISSIONS as $object => $permissions) {
            foreach ($permissions as $permission) {
                $result = $connection->selectOne(
                    'SELECT HAS_PERMS_BY_NAME(?, ?, ?) AS has_permission',
                    [$object, 'OBJECT', $permission],
                );

                if ((int) ($result->has_permission ?? 0) === 1) {
                    $this->components->error("Unsafe SQL permission: {$permission} on {$object}");
                    $valid = false;
                }
            }
        }

        if ($valid) {
            $this->components->info('Required adapter object permissions are available.');
        }

        return $valid;
    }

    /**
     * Confirms SQL Server exposes the authoritative item serial procedure.
     */
    private function verifyItemSerialSource(Connection $connection): bool
    {
        if ($connection->getDriverName() !== 'sqlsrv') {
            $this->components->warn('Item serial procedure verification skipped for a non-SQL Server test connection.');

            return true;
        }

        $procedure = $connection->selectOne(
            'SELECT OBJECT_ID(?, ?) AS procedure_id',
            ['dbo.WZ_GetItemSerial', 'P'],
        );

        if ($procedure === null || $procedure->procedure_id === null) {
            $this->components->error('Missing required game procedure: dbo.WZ_GetItemSerial');

            return false;
        }

        $this->components->info('Authoritative item serial procedure is available.');

        return true;
    }

    /**
     * Verifies required tables and columns without reading player records.
     */
    private function verifySchema(
        Connection $connection,
        GameDatabaseProfile $profile,
    ): bool {
        $schema = $connection->getSchemaBuilder();
        $valid = true;

        foreach ($profile->requiredDatabaseSchema() as $table => $requiredColumns) {
            if (! $schema->hasTable($table)) {
                $this->components->error("Missing required game table: {$table}");
                $valid = false;

                continue;
            }

            $availableColumns = array_map(
                static fn (string $column): string => strtolower($column),
                $schema->getColumnListing($table),
            );

            foreach ($requiredColumns as $column) {
                if (! in_array(strtolower($column), $availableColumns, true)) {
                    $this->components->error("Missing required game column: {$table}.{$column}");
                    $valid = false;
                }
            }
        }

        if ($valid) {
            $this->components->info('Required game profile schema is available.');
        }

        return $valid;
    }

    /**
     * Rejects sysadmin and db_owner SQL Server identities for production use.
     */
    private function verifyPermissions(Connection $connection): bool
    {
        if ($connection->getDriverName() !== 'sqlsrv') {
            $this->components->warn('SQL role verification skipped for a non-SQL Server test connection.');

            return true;
        }

        $permissions = $connection->selectOne(
            'SELECT IS_SRVROLEMEMBER(?) AS is_sysadmin, IS_MEMBER(?) AS is_db_owner',
            ['sysadmin', 'db_owner'],
        );
        $isPrivileged = (int) ($permissions->is_sysadmin ?? 0) === 1
            || (int) ($permissions->is_db_owner ?? 0) === 1;

        if ($isPrivileged) {
            $this->components->error(
                'Unsafe SQL identity: game connection must not use sysadmin or db_owner in production.',
            );

            return false;
        }

        $this->components->info('SQL identity is not sysadmin or db_owner.');

        return true;
    }

    /**
     * Confirms the deployment starts with both application write switches closed.
     */
    private function verifySafetyConfiguration(): bool
    {
        $readOnly = (bool) config('database.connections.game.readonly', true);
        $writesEnabled = (bool) config('game.writes_enabled', false);

        if (! $readOnly || $writesEnabled) {
            $this->components->error(
                'Unsafe startup flags: require GAME_DB_READONLY=true and GAME_WRITES_ENABLED=false.',
            );

            return false;
        }

        $this->components->info('Game write safety switches are closed.');

        return true;
    }
}
