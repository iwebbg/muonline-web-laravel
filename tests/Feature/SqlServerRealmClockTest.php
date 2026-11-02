<?php

namespace Tests\Feature;

use App\Game\Clocks\SqlServerRealmClock;
use App\Game\Clocks\SystemRealmClock;
use DateTimeImmutable;
use Illuminate\Database\DatabaseManager;
use Psr\Log\NullLogger;
use Tests\TestCase;

/**
 * Verifies resilient realm time when the remote database clock is unavailable.
 */
final class SqlServerRealmClockTest extends TestCase
{
    /**
     * Confirms that a failed SQL time query falls back to the web server clock.
     */
    public function test_unavailable_database_clock_falls_back_to_web_server_time(): void
    {
        config()->set('game.event_timezone', 'Europe/Sofia');
        config()->set('database.connections.game', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $this->travelTo(new DateTimeImmutable('2026-11-02T10:00:00+00:00'));

        $database = $this->app->make(DatabaseManager::class);

        $database->purge('game');

        $clock = new SqlServerRealmClock(
            database: $database,
            fallbackClock: new SystemRealmClock,
            logger: new NullLogger,
        );

        $serverNow = $clock->now();

        $this->assertSame('2026-11-02T12:00:00+02:00', $serverNow->format(DATE_ATOM));
    }
}
