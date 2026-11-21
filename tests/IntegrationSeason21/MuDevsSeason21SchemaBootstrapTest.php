<?php

namespace Tests\IntegrationSeason21;

use App\Game\Versions\Contracts\GameDatabaseProfile;
use App\Game\Versions\Contracts\GameDatabaseSchemaBootstrap;
use App\Game\Versions\MuDevsSeason21\MuDevsSeason21Profile;
use App\Installation\GameDatabaseInstallation;
use App\Installation\SqlServerGameDatabaseInstaller;
use Illuminate\Database\DatabaseManager;
use PDO;
use RuntimeException;
use Tests\Integration\TestCase;

/**
 * Verifies opt-in S21 schema preparation after principal creation without changing legacy bootstrap order.
 */
final class MuDevsSeason21SchemaBootstrapTest extends TestCase
{
    private const DATABASE = 'MuOnline_S21_Bootstrap_Test';

    private const LOGIN = 's21_bootstrap_user';

    private const ROLE = 's21_bootstrap_role';

    /**
     * Creates native receipt storage and grants only reads after login, user and role exist.
     */
    public function test_bootstrap_prepares_native_schema_after_principals_and_before_object_grants(): void
    {
        $database = $this->app->make(DatabaseManager::class);
        $adminConfiguration = config('database.connections.game');
        $adminConfiguration['database'] = 'master';
        config()->set('database.connections.game_admin', $adminConfiguration);
        $database->purge('game_admin');
        $pdo = $database->connection('game_admin')->getPdo();
        $pdo->exec('CREATE DATABASE [MuOnline_S21_Bootstrap_Test]');
        config()->set('database.connections.game.database', self::DATABASE);
        config()->set('database.connections.game.username', self::LOGIN);
        config()->set('database.connections.game.password', 'S21-bootstrap-only-123!');
        $database->purge('game');

        try {
            $resource = (new MuDevsSeason21Profile)->bootstrapSchemaExtensionResource();
            $profile = $this->bootstrapProfile($pdo, $resource);
            $installer = new SqlServerGameDatabaseInstaller($database, $profile);
            $installation = new GameDatabaseInstallation(self::DATABASE, self::LOGIN, 'S21-bootstrap-only-123!', self::ROLE);
            $installer->install($installation);
            $installer->install($installation);
            $runtime = $database->connection('game');
            $identity = $runtime->selectOne('SELECT SUSER_SNAME() AS login_name');
            $permissions = $runtime->selectOne(
                "SELECT HAS_PERMS_BY_NAME('dbo.IWEB_S21_ItemOperation', 'OBJECT', 'SELECT') AS can_read, "
                ."HAS_PERMS_BY_NAME('dbo.IWEB_S21_ItemOperation', 'OBJECT', 'INSERT') AS can_insert, "
                ."IS_SRVROLEMEMBER('sysadmin') AS is_sysadmin, IS_MEMBER('db_owner') AS is_owner",
            );

            self::assertSame(self::LOGIN, $identity->login_name);
            self::assertSame(1, (int) $permissions->can_read);
            self::assertSame(0, (int) $permissions->can_insert);
            self::assertSame(0, (int) $permissions->is_sysadmin);
            self::assertSame(0, (int) $permissions->is_owner);
            self::assertSame(0, $runtime->table('IWEB_S21_ItemOperation')->count());
        } finally {
            $database->purge('game');
            $pdo->exec('USE [master]');
            $pdo->exec('DROP DATABASE [MuOnline_S21_Bootstrap_Test]');
            $pdo->exec('DROP LOGIN [s21_bootstrap_user]');
            $database->purge('game_admin');
        }
    }

    /**
     * Supplies a minimal profile that asserts the SQL principal precondition at extension lookup.
     */
    private function bootstrapProfile(PDO $pdo, string $resource): GameDatabaseProfile
    {
        return new class($pdo, $resource, self::LOGIN, self::ROLE) implements GameDatabaseProfile, GameDatabaseSchemaBootstrap
        {
            /**
             * Retains the bootstrap session so schema lookup can verify its principal state.
             */
            public function __construct(
                private readonly PDO $pdo,
                private readonly string $resource,
                private readonly string $login,
                private readonly string $role,
            ) {}

            /**
             * Requires the native receipt fields used by the minimal bootstrap fixture.
             *
             * @return array<string, list<string>>
             */
            public function requiredDatabaseSchema(): array
            {
                return ['IWEB_S21_ItemOperation' => ['OperationKey', 'Direction', 'AccountID', 'PayloadHash']];
            }

            /**
             * Keeps the test runtime principal unable to mutate receipt or game data.
             *
             * @return array<string, list<string>>
             */
            public function databaseObjectPermissions(): array
            {
                return ['dbo.IWEB_S21_ItemOperation' => ['SELECT']];
            }

            /**
             * Fails if schema preparation is requested before the target principals exist.
             */
            public function bootstrapSchemaExtensionResource(): string
            {
                $statement = $this->pdo->prepare('SELECT USER_ID(?) AS user_id, DATABASE_PRINCIPAL_ID(?) AS role_id');
                $statement->execute([$this->login, $this->role]);
                $principals = $statement->fetch(PDO::FETCH_ASSOC);

                if ((int) ($principals['user_id'] ?? 0) < 1 || (int) ($principals['role_id'] ?? 0) < 1) {
                    throw new RuntimeException('Schema preparation was requested before the runtime principals existed.');
                }

                return $this->resource;
            }
        };
    }
}
