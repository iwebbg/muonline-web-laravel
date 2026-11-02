<?php

namespace App\Console\Commands;

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
#[Description('Verify the 97d+99i game database connection, schema and SQL permissions.')]
final class DiagnoseGameDatabase extends Command
{
    /**
     * Lists the minimum legacy columns required by the active application profile.
     *
     * @var array<string, list<string>>
     */
    private const REQUIRED_SCHEMA = [
        'MEMB_INFO' => ['memb_guid', 'memb___id', 'memb__pwd', 'mail_addr', 'bloc_code', 'ctl1_code'],
        'Character' => ['AccountID', 'Name', 'cLevel', 'Class', 'Money', 'Resets', 'GrandResets'],
        'warehouse' => ['AccountID', 'Items', 'Money'],
        'MEMB_STAT' => ['memb___id', 'ConnectStat'],
        'Guild' => ['G_Name', 'G_Master', 'G_Score'],
        'GuildMember' => ['Name', 'G_Name'],
        'GameServerInfo' => ['ItemCount'],
        'VI_CURR_INFO' => ['memb___id', 'memb_guid'],
    ];

    /**
     * Lists every object permission required by enabled 97d+99i write adapters.
     *
     * @var array<string, list<string>>
     */
    private const REQUIRED_OBJECT_PERMISSIONS = [
        'dbo.MEMB_INFO' => ['SELECT', 'INSERT', 'UPDATE'],
        'dbo.MEMB_STAT' => ['SELECT'],
        'dbo.Character' => ['SELECT', 'UPDATE'],
        'dbo.warehouse' => ['SELECT', 'UPDATE'],
        'dbo.Guild' => ['SELECT'],
        'dbo.GuildMember' => ['SELECT'],
        'dbo.VI_CURR_INFO' => ['SELECT', 'INSERT'],
        'dbo.GameServerInfo' => ['SELECT'],
        'dbo.WZ_GetItemSerial' => ['EXECUTE', 'VIEW DEFINITION'],
    ];

    /**
     * Executes read-only diagnostics and fails on unsafe production boundaries.
     */
    public function handle(DatabaseManager $database): int
    {
        try {
            $connection = $database->connection('game');
            $connection->selectOne('SELECT CURRENT_TIMESTAMP AS server_time');
        } catch (Throwable) {
            $this->components->error('Game database connection failed.');

            return self::FAILURE;
        }

        $this->components->info('Game database connection is available.');
        $schemaIsValid = $this->verifySchema($connection);
        $permissionsAreSafe = $this->verifyPermissions($connection);
        $itemSerialSourceIsAvailable = $this->verifyItemSerialSource($connection);
        $objectPermissionsAreAvailable = $this->verifyObjectPermissions($connection);
        $configurationIsSafe = $this->verifySafetyConfiguration();

        if (
            ! $schemaIsValid
            || ! $permissionsAreSafe
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
     * Confirms the SQL identity has the exact read and write capabilities used by adapters.
     */
    private function verifyObjectPermissions(Connection $connection): bool
    {
        if ($connection->getDriverName() !== 'sqlsrv') {
            $this->components->warn('Object permission verification skipped for a non-SQL Server test connection.');

            return true;
        }

        $valid = true;

        foreach (self::REQUIRED_OBJECT_PERMISSIONS as $object => $permissions) {
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
    private function verifySchema(Connection $connection): bool
    {
        $schema = $connection->getSchemaBuilder();
        $valid = true;

        foreach (self::REQUIRED_SCHEMA as $table => $requiredColumns) {
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
            $this->components->info('Required 97d+99i schema is available.');
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
