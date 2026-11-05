<?php

namespace App\Installation;

use App\Installation\Contracts\ApplicationDatabaseInstaller;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use PDO;

/**
 * Provisions the Laravel-owned database and its least-privilege SQL Server login.
 */
final class SqlServerApplicationDatabaseInstaller implements ApplicationDatabaseInstaller
{
    /**
     * Creates the installer with Laravel's configured database connection manager.
     */
    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    /**
     * Creates the database, login, user and migration-capable application role idempotently.
     */
    public function install(ApplicationDatabaseInstallation $installation): void
    {
        $databaseName = $this->quoteIdentifier($installation->databaseName);
        $loginName = $this->quoteIdentifier($installation->loginName);
        $roleName = $this->quoteIdentifier($installation->roleName);
        $password = $this->quoteStringLiteral($installation->loginPassword);
        $connectionName = (string) config(
            'application_database.bootstrap.admin_connection',
            'application_admin',
        );

        try {
            $pdo = $this->database->connection($connectionName)->getPdo();

            if ($this->scalar($pdo, 'SELECT DB_ID(?)', [$installation->databaseName]) === null) {
                $pdo->exec("CREATE DATABASE {$databaseName}");
            }

            if ($this->scalar($pdo, 'SELECT SUSER_ID(?)', [$installation->loginName]) === null) {
                $pdo->exec(
                    "CREATE LOGIN {$loginName} WITH PASSWORD = {$password}, "
                    .'CHECK_POLICY = ON, CHECK_EXPIRATION = OFF',
                );
            }

            $pdo->exec("USE {$databaseName}");

            if ($this->scalar($pdo, 'SELECT USER_ID(?)', [$installation->loginName]) === null) {
                $pdo->exec(
                    "CREATE USER {$loginName} FOR LOGIN {$loginName} "
                    .'WITH DEFAULT_SCHEMA = [dbo]',
                );
            }

            if ($this->scalar(
                $pdo,
                'SELECT DATABASE_PRINCIPAL_ID(?)',
                [$installation->roleName],
            ) === null) {
                $pdo->exec("CREATE ROLE {$roleName} AUTHORIZATION [dbo]");
            }

            $isRoleMember = $this->scalar(
                $pdo,
                'SELECT IS_ROLEMEMBER(?, ?)',
                [$installation->roleName, $installation->loginName],
            );

            if ((int) $isRoleMember !== 1) {
                $pdo->exec("ALTER ROLE {$roleName} ADD MEMBER {$loginName}");
            }

            $pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON SCHEMA::[dbo] TO {$roleName}");
            $pdo->exec("GRANT EXECUTE, REFERENCES, VIEW DEFINITION ON SCHEMA::[dbo] TO {$roleName}");
            $pdo->exec("GRANT CREATE TABLE TO {$roleName}");
            $pdo->exec("GRANT ALTER ON SCHEMA::[dbo] TO {$roleName}");
        } finally {
            $this->database->purge($connectionName);
        }

        $this->verifyRuntimeConnection();
    }

    /**
     * Reads one scalar SQL Server value with bound parameters.
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
     * Accepts only predictable SQL Server identifiers before quoting them.
     */
    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,127}\z/', $identifier) !== 1) {
            throw new InvalidArgumentException('Unsafe SQL Server identifier.');
        }

        return '['.$identifier.']';
    }

    /**
     * Quotes the runtime login password as a Unicode SQL Server string literal.
     */
    private function quoteStringLiteral(string $value): string
    {
        if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new InvalidArgumentException('Unsafe SQL Server password value.');
        }

        return "N'".str_replace("'", "''", $value)."'";
    }

    /**
     * Confirms that the restricted login can open the newly provisioned database.
     */
    private function verifyRuntimeConnection(): void
    {
        $connectionName = (string) config('database.default', 'sqlsrv');

        try {
            $this->database->connection($connectionName)->getPdo();
        } finally {
            $this->database->purge($connectionName);
        }
    }
}
