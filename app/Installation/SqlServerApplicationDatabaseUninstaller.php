<?php

namespace App\Installation;

use App\Installation\Contracts\ApplicationDatabaseUninstaller;
use DomainException;
use Illuminate\Database\DatabaseManager;
use PDO;
use Throwable;

/**
 * Removes verified web resources using a short-lived administrator connection to master.
 */
final class SqlServerApplicationDatabaseUninstaller implements ApplicationDatabaseUninstaller
{
    private const CONNECTION = 'application_uninstall_admin';

    /**
     * Uses an isolated connection without changing configured bootstrap credentials.
     */
    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * Preflights both resources before nontransactional DROP statements and reports progress.
     */
    public function uninstall(ApplicationDatabaseRemoval $removal): ApplicationDatabaseRemovalResult
    {
        $databaseStatus = 'not_checked';
        $loginStatus = 'not_checked';

        try {
            $this->validateTarget($removal);
            $configuration = config('database.connections.sqlsrv');
            $configuration['url'] = null;
            $configuration['database'] = 'master';
            $configuration['username'] = $removal->adminUsername;
            $configuration['password'] = $removal->adminPassword;
            $configuration['appname'] = 'MuOnline Web Database Uninstall';
            $pdo = $this->database->connectUsing(self::CONNECTION, $configuration, force: true)->getPdo();

            if ((int) $this->scalar($pdo, "SELECT IS_SRVROLEMEMBER('sysadmin')") !== 1) {
                throw new DomainException('sysadmin_required');
            }

            $databaseExists = $this->scalar($pdo, 'SELECT DB_ID(?)', [$removal->databaseName]) !== null;
            $loginExists = $this->scalar($pdo, 'SELECT SUSER_ID(?)', [$removal->loginName]) !== null;
            $databaseStatus = $databaseExists ? 'retained' : 'already_absent';
            $loginStatus = $loginExists ? 'retained' : 'already_absent';

            if ($loginExists) {
                $this->validateLogin($pdo, $removal);
            }

            if ($databaseExists) {
                $this->validateDatabase($pdo, $removal, $loginExists);
            }

            $this->database->purge('sqlsrv');

            if ($databaseExists) {
                $databaseStatus = 'unknown';
                $this->dropDatabase($pdo, $removal->databaseName);
                $databaseStatus = 'removed';
            }

            if ($loginExists) {
                $this->validateLogin($pdo, $removal);
                $login = $this->quoteIdentifier($removal->loginName);
                $loginStatus = 'unknown';
                $pdo->exec("DROP LOGIN {$login}");
                $loginStatus = 'removed';
            }

            return new ApplicationDatabaseRemovalResult(true, 'uninstalled', $databaseStatus, $loginStatus);
        } catch (DomainException $exception) {
            return new ApplicationDatabaseRemovalResult(false, $exception->getMessage(), $databaseStatus, $loginStatus);
        } catch (Throwable) {
            return new ApplicationDatabaseRemovalResult(false, 'sql_operation_failed', $databaseStatus, $loginStatus, retryable: true);
        } finally {
            $this->database->purge(self::CONNECTION);
        }
    }

    /**
     * Matches all confirmed coordinates and excludes game/system resources before connecting.
     */
    private function validateTarget(ApplicationDatabaseRemoval $removal): void
    {
        $configuration = config('database.connections.sqlsrv', []);

        if (config('database.default') !== 'sqlsrv'
            || ($configuration['driver'] ?? null) !== 'sqlsrv'
            || ! empty($configuration['url'])
            || $removal->host !== ($configuration['host'] ?? null)
            || $removal->port !== (int) ($configuration['port'] ?? 0)
            || $removal->databaseName !== ($configuration['database'] ?? null)
            || $removal->loginName !== ($configuration['username'] ?? null)
            || preg_match('/\A[A-Za-z0-9_.:\[\]-]+\z/', $removal->host) !== 1
            || $removal->port < 1 || $removal->port > 65535) {
            throw new DomainException('target_mismatch');
        }

        foreach ([$removal->databaseName, $removal->loginName] as $identifier) {
            if (preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,127}\z/', $identifier) !== 1) {
                throw new DomainException('unsafe_identifier');
            }
        }

        if ($removal->adminUsername === '' || $removal->adminPassword === '') {
            throw new DomainException('admin_credentials_required');
        }

        $protectedDatabases = ['master', 'model', 'msdb', 'tempdb', 'distribution', 'MuOnline', 'BattleCore', 'Ranking'];
        $protectedLogins = ['sa', 'root', $removal->adminUsername];

        foreach (['game', 'ranking', 'battle_core', 'game_admin'] as $connection) {
            $protectedDatabases[] = (string) config("database.connections.{$connection}.database", '');
            $protectedLogins[] = (string) config("database.connections.{$connection}.username", '');
        }

        foreach ($protectedDatabases as $name) {
            if (strcasecmp($removal->databaseName, $name) === 0) {
                throw new DomainException('protected_database');
            }
        }

        foreach ($protectedLogins as $name) {
            if (strcasecmp($removal->loginName, $name) === 0) {
                throw new DomainException('protected_login');
            }
        }
    }

    /**
     * Rejects privileged, shared, owning or actively connected cross-database principals.
     */
    private function validateLogin(PDO $pdo, ApplicationDatabaseRemoval $removal): void
    {
        $login = $removal->loginName;
        $principal = $this->scalar($pdo, "SELECT COUNT(*) FROM sys.server_principals WHERE name = ? AND type = 'S' AND sid <> 0x01", [$login]);
        $roles = $this->scalar($pdo, 'SELECT COUNT(*) FROM sys.server_role_members WHERE member_principal_id = SUSER_ID(?)', [$login]);
        $permissions = $this->scalar($pdo, "SELECT COUNT(*) FROM sys.server_permissions WHERE grantee_principal_id = SUSER_ID(?) AND NOT (permission_name = 'CONNECT SQL' AND state = 'G')", [$login]);

        if ((int) $principal !== 1 || (int) $roles !== 0 || (int) $permissions !== 0) {
            throw new DomainException('privileged_login');
        }

        $statement = $pdo->query('SELECT name, state_desc, owner_sid FROM sys.databases');

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $database) {
            if (strcasecmp($database['name'], $removal->databaseName) === 0) {
                continue;
            }

            if ($database['state_desc'] !== 'ONLINE') {
                throw new DomainException('cannot_verify_shared_login');
            }

            $identifier = $this->quoteIdentifier($database['name']);
            $mapped = $this->scalar($pdo, "SELECT COUNT(*) FROM {$identifier}.sys.database_principals WHERE sid = SUSER_SID(?)", [$login]);
            $owned = $this->scalar($pdo, 'SELECT COUNT(*) FROM sys.databases WHERE name = ? AND owner_sid = SUSER_SID(?)', [$database['name'], $login]);

            if ((int) $mapped !== 0 || (int) $owned !== 0) {
                throw new DomainException('shared_login');
            }
        }

        $jobs = $this->scalar($pdo, 'SELECT COUNT(*) FROM msdb.dbo.sysjobs WHERE owner_sid = SUSER_SID(?)', [$login]);
        $ownedPrincipals = $this->scalar($pdo, 'SELECT COUNT(*) FROM sys.server_principals WHERE owning_principal_id = SUSER_ID(?)', [$login]);
        $ownedEndpoints = $this->scalar($pdo, 'SELECT COUNT(*) FROM sys.endpoints WHERE principal_id = SUSER_ID(?)', [$login]);
        $credentials = $this->scalar($pdo, 'SELECT COUNT(*) FROM sys.server_principal_credentials WHERE principal_id = SUSER_ID(?)', [$login]);

        if ((int) $jobs !== 0 || (int) $ownedPrincipals !== 0 || (int) $ownedEndpoints !== 0 || (int) $credentials !== 0) {
            throw new DomainException('shared_login');
        }
    }

    /**
     * Requires the bootstrap role/user and refuses game tables or other mapped SQL users.
     */
    private function validateDatabase(PDO $pdo, ApplicationDatabaseRemoval $removal, bool $loginExists): void
    {
        $name = $this->quoteIdentifier($removal->databaseName);
        $state = $this->scalar($pdo, 'SELECT state_desc FROM sys.databases WHERE name = ? AND database_id > 4 AND source_database_id IS NULL', [$removal->databaseName]);

        if ($state !== 'ONLINE') {
            throw new DomainException('database_unavailable');
        }

        $role = $this->scalar($pdo, "SELECT COUNT(*) FROM {$name}.sys.database_principals WHERE name = ? AND type = 'R'", [(string) config('application_database.application_role')]);
        $user = $this->scalar($pdo, "SELECT COUNT(*) FROM {$name}.sys.database_principals WHERE name = ? AND type = 'S'", [$removal->loginName]);
        $tables = (int) $this->scalar($pdo, "SELECT COUNT(*) FROM {$name}.sys.tables WHERE is_ms_shipped = 0");
        $appTables = (int) $this->scalar($pdo, "SELECT COUNT(*) FROM {$name}.sys.tables WHERE name IN ('administrators', 'migrations') AND schema_id = 1");
        $gameTables = (int) $this->scalar($pdo, "SELECT COUNT(*) FROM {$name}.sys.tables WHERE name IN ('MEMB_INFO', 'Character', 'warehouse', 'Guild', 'AccountCharacter')");

        if ((int) $role !== 1 || (int) $user !== 1 || $gameTables !== 0 || ($tables > 0 && $appTables !== 2)) {
            throw new DomainException('unverified_web_database');
        }

        $otherUsers = $this->scalar($pdo, "SELECT COUNT(*) FROM {$name}.sys.database_principals WHERE principal_id > 4 AND type IN ('S', 'U', 'G') AND name <> ?", [$removal->loginName]);

        if ((int) $otherUsers !== 0) {
            throw new DomainException('shared_database');
        }

        if ($loginExists) {
            $matchingUser = $this->scalar($pdo, "SELECT COUNT(*) FROM {$name}.sys.database_principals WHERE name = ? AND sid = SUSER_SID(?)", [$removal->loginName, $removal->loginName]);

            if ((int) $matchingUser !== 1) {
                throw new DomainException('shared_database');
            }
        }
    }

    /**
     * Drops only the quoted target and restores multi-user access if removal fails.
     */
    private function dropDatabase(PDO $pdo, string $databaseName): void
    {
        $identifier = $this->quoteIdentifier($databaseName);

        try {
            $pdo->exec("ALTER DATABASE {$identifier} SET SINGLE_USER WITH ROLLBACK IMMEDIATE; DROP DATABASE {$identifier};");
        } catch (Throwable $exception) {
            if ($this->scalar($pdo, 'SELECT DB_ID(?)', [$databaseName]) !== null) {
                $pdo->exec("ALTER DATABASE {$identifier} SET MULTI_USER WITH ROLLBACK IMMEDIATE");
            }

            throw $exception;
        }
    }

    /**
     * Reads metadata with bound values, never interpolating credentials.
     *
     * @param  list<string>  $bindings
     */
    private function scalar(PDO $pdo, string $query, array $bindings = []): mixed
    {
        $statement = $pdo->prepare($query);
        $statement->execute($bindings);
        $value = $statement->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * Escapes SQL Server identifiers obtained from validated targets or system metadata.
     */
    private function quoteIdentifier(string $identifier): string
    {
        return '['.str_replace(']', ']]', $identifier).']';
    }
}
