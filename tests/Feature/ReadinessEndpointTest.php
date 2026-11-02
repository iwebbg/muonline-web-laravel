<?php

namespace Tests\Feature;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifies the public readiness contract without exposing dependency details.
 */
final class ReadinessEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms healthy dependencies produce the minimal successful monitoring response.
     */
    public function test_ready_dependencies_return_minimal_success_response(): void
    {
        config([
            'cache.default' => 'array',
            'game.data_source' => 'fixture',
        ]);

        $response = $this->getJson('/ready');

        $response
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
            ]);
    }

    /**
     * Confirms a dependency failure returns 503 without leaking connection or exception data.
     */
    public function test_failed_dependency_returns_minimal_service_unavailable_response(): void
    {
        config([
            'database.default' => 'missing-readiness-connection',
            'cache.default' => 'array',
            'game.data_source' => 'fixture',
        ]);

        $response = $this->getJson('/ready');

        $response
            ->assertServiceUnavailable()
            ->assertExactJson([
                'status' => 'unavailable',
            ]);
    }

    /**
     * Confirms a failed live game connection makes the service unavailable without leaking its path.
     */
    public function test_failed_game_database_returns_service_unavailable_without_details(): void
    {
        $unavailableDatabase = '/missing-readiness-directory/game.sqlite';

        config([
            'cache.default' => 'array',
            'game.data_source' => 'database',
            'game.database_connections.primary' => 'game',
            'database.connections.game' => [
                'driver' => 'sqlite',
                'database' => $unavailableDatabase,
                'prefix' => '',
            ],
        ]);

        $response = $this->getJson('/ready');

        $response
            ->assertServiceUnavailable()
            ->assertExactJson([
                'status' => 'unavailable',
            ]);
        $response->assertDontSee($unavailableDatabase);
    }

    /**
     * Confirms a cache outage is detected while its exception remains private.
     */
    public function test_failed_cache_round_trip_returns_service_unavailable_without_details(): void
    {
        $cache = $this->mock(CacheRepository::class);
        $cache
            ->shouldReceive('put')
            ->once()
            ->andThrow(new RuntimeException('private-cache-failure'));
        $cache
            ->shouldReceive('forget')
            ->once()
            ->andReturn(true);

        config([
            'game.data_source' => 'fixture',
        ]);

        $response = $this->getJson('/ready');

        $response
            ->assertServiceUnavailable()
            ->assertExactJson([
                'status' => 'unavailable',
            ]);
        $response->assertDontSee('private-cache-failure');
    }

    /**
     * Confirms monitoring stays available when the stateful browser session backend is down.
     */
    public function test_readiness_does_not_depend_on_browser_session_storage(): void
    {
        config([
            'cache.default' => 'array',
            'game.data_source' => 'fixture',
            'session.driver' => 'redis',
            'session.connection' => 'readiness-unavailable',
            'database.redis.readiness-unavailable' => [
                'host' => '127.0.0.1',
                'port' => 1,
                'database' => 15,
            ],
        ]);

        $response = $this->getJson('/ready');

        $response
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
            ]);
    }
}
