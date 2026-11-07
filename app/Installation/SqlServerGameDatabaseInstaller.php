<?php

namespace App\Installation;

use App\Game\Versions\Contracts\GameDatabaseProfile;
use App\Installation\Contracts\GameDatabaseInstaller;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use PDO;

/**
 * Provisions a least-privilege SQL Server identity for supported game adapters.
 */
final class SqlServerGameDatabaseInstaller implements GameDatabaseInstaller
{
    /**
     * Creates the installer with Laravel's configured database connection manager.
     */
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly GameDatabaseProfile $profile,
    ) {}

    /**
     * Creates the login, database user, role and adapter permissions without changing game data.
     */
    public function install(GameDatabaseInstallation $installation): void
    {
        $databaseName = $this->quoteIdentifier($installation->databaseName);
        $loginName = $this->quoteIdentifier($installation->loginName);
        $roleName = $this->quoteIdentifier($installation->roleName);
        $password = $this->quoteStringLiteral($installation->loginPassword);
        $connectionName = (string) config(
            'game.database_bootstrap.admin_connection',
            'game_admin',
        );

        try {
            $pdo = $this->database->connection($connectionName)->getPdo();

            if ($this->scalar($pdo, 'SELECT DB_ID(?)', [$installation->databaseName]) === null) {
                throw new InvalidArgumentException('The configured game database does not exist.');
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

            foreach ($this->profile->databaseObjectPermissions() as $object => $permissions) {
                $quotedObject = $this->quoteMultipartIdentifier($object);

                foreach ($permissions as $permission) {
                    $pdo->exec("GRANT {$permission} ON OBJECT::{$quotedObject} TO {$roleName}");
                }
            }
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
     * Quotes one allow-listed SQL Server identifier.
     */
    private function quoteIdentifier(string $identifier): string
    {
        if (preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,127}\z/', $identifier) !== 1) {
            throw new InvalidArgumentException('Unsafe SQL Server identifier.');
        }

        return '['.$identifier.']';
    }

    /**
     * Quotes one allow-listed schema-qualified SQL Server object name.
     */
    private function quoteMultipartIdentifier(string $identifier): string
    {
        $parts = explode('.', $identifier);

        if (count($parts) !== 2) {
            throw new InvalidArgumentException('Unsafe SQL Server object identifier.');
        }

        return implode('.', array_map($this->quoteIdentifier(...), $parts));
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
     * Confirms the restricted login can open the configured game database.
     */
    private function verifyRuntimeConnection(): void
    {
        try {
            $this->database->connection('game')->getPdo();
        } finally {
            $this->database->purge('game');
        }
    }
}
