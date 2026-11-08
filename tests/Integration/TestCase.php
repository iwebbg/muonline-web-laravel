<?php

namespace Tests\Integration;

use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

/**
 * Boots integration tests only against the disposable SQL Server service.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Verifies the isolated service coordinates before any integration query runs.
     */
    public function createApplication(): Application
    {
        $application = parent::createApplication();
        $configuration = $application['config']->get('database.connections.game', []);
        $expectedDatabase = getenv('INTEGRATION_GAME_DATABASE') ?: 'MuOnline';

        if (
            ($configuration['driver'] ?? null) !== 'sqlsrv'
            || ($configuration['host'] ?? null) !== 'sqlserver-test'
            || ($configuration['database'] ?? null) !== $expectedDatabase
            || ($configuration['username'] ?? null) !== 'sa'
        ) {
            throw new RuntimeException('Refusing to run integration tests outside the disposable SQL Server service.');
        }

        $connection = $application->make(DatabaseManager::class)->connection('game');

        if ($connection->getDriverName() !== 'sqlsrv' || $connection->getDatabaseName() !== $expectedDatabase) {
            throw new RuntimeException(sprintf(
                'The disposable integration database did not resolve to SQL Server %s.',
                $expectedDatabase,
            ));
        }

        return $application;
    }
}
