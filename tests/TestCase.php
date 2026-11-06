<?php

namespace Tests;

use App\Game\Contracts\RealmReadRepository;
use App\Game\Repositories\FixtureRealmReadRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

/**
 * Provides project-wide isolation from the live MuOnline database.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Boots Laravel only when its resolved default database is SQLite in memory.
     */
    public function createApplication(): Application
    {
        $application = parent::createApplication();

        $this->assertIsolatedTestingDatabase($application);
        $this->blockExternalDatabaseDrivers($application);
        $this->replaceExternalDatabaseConnections($application);

        return $application;
    }

    /**
     * Forces public realm reads to fixtures unless a test explicitly opts in.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        config()->set('game.data_source', 'fixture');
        $this->app->bind(RealmReadRepository::class, FixtureRealmReadRepository::class);
    }

    /**
     * Refuses to continue before database-refresh traits can run against SQL Server.
     */
    private function assertIsolatedTestingDatabase(Application $application): void
    {
        $environment = $application->environment();
        $defaultConnection = $application['config']->get('database.default');
        $database = $application->make(DatabaseManager::class)->connection();
        $driver = $database->getDriverName();
        $databaseName = $database->getDatabaseName();

        if (
            $environment !== 'testing'
            || $defaultConnection !== 'sqlite'
            || $driver !== 'sqlite'
            || $databaseName !== ':memory:'
        ) {
            throw new RuntimeException(sprintf(
                'Refusing to start tests with environment [%s], connection [%s], driver [%s], and database [%s].',
                $environment,
                is_string($defaultConnection) ? $defaultConnection : '<invalid>',
                $driver,
                $databaseName,
            ));
        }
    }

    /**
     * Makes every server-based database driver fail before opening a connection.
     */
    private function blockExternalDatabaseDrivers(Application $application): void
    {
        $database = $application->make(DatabaseManager::class);
        $externalDrivers = ['mariadb', 'mysql', 'pgsql', 'sqlsrv'];

        foreach ($externalDrivers as $externalDriver) {
            $database->extend(
                $externalDriver,
                function (array $configuration, string $connectionName): never {
                    throw new RuntimeException(sprintf(
                        'Database connection [%s] uses forbidden test driver [%s].',
                        $connectionName,
                        $configuration['driver'] ?? '<unset>',
                    ));
                },
            );
        }
    }

    /**
     * Replaces every non-default database connection with isolated SQLite memory.
     */
    private function replaceExternalDatabaseConnections(Application $application): void
    {
        $connections = $application['config']->get('database.connections', []);
        $database = $application->make(DatabaseManager::class);

        foreach (array_keys($connections) as $connectionName) {
            if ($connectionName === 'sqlite') {
                continue;
            }

            $application['config']->set('database.connections.'.$connectionName, [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
            $database->purge($connectionName);
        }
    }
}
