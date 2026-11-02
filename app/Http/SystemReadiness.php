<?php

namespace App\Http;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;
use Throwable;

/**
 * Measures the minimum runtime dependencies required to serve authoritative data safely.
 */
final class SystemReadiness
{
    /**
     * Creates the readiness probe with application database and cache access.
     */
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * Returns a non-sensitive readiness summary for monitoring and administration.
     *
     * @return array{ready: bool, checks: array{applicationDatabase: bool, cache: bool, gameDatabase: bool}}
     */
    public function inspect(): array
    {
        $checks = [
            'applicationDatabase' => $this->databaseConnectionIsAvailable(),
            'cache' => $this->cacheIsAvailable(),
            'gameDatabase' => $this->gameDatabaseIsAvailable(),
        ];

        return [
            'ready' => ! in_array(false, $checks, true),
            'checks' => $checks,
        ];
    }

    /**
     * Verifies the application-owned database can answer a minimal read query.
     */
    private function databaseConnectionIsAvailable(): bool
    {
        try {
            $this->database->connection()->selectOne('SELECT 1 AS ready');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Verifies the configured cache can round-trip and remove an ephemeral probe value.
     */
    private function cacheIsAvailable(): bool
    {
        $cacheKey = 'system-readiness:'.getmypid().':'.str_replace('.', '', (string) microtime(true));
        $cacheValue = 'ready';

        try {
            $this->cache->put($cacheKey, $cacheValue, 5);

            return $this->cache->get($cacheKey) === $cacheValue;
        } catch (Throwable) {
            return false;
        } finally {
            try {
                $this->cache->forget($cacheKey);
            } catch (Throwable) {
            }
        }
    }

    /**
     * Verifies the read model connection when the active profile uses live game data.
     */
    private function gameDatabaseIsAvailable(): bool
    {
        if (config('game.data_source') !== 'database') {
            return true;
        }

        $connectionName = config('game.database_connections.primary', 'game');

        if (! is_string($connectionName) || $connectionName === '') {
            return false;
        }

        try {
            $this->database->connection($connectionName)->selectOne('SELECT 1 AS ready');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
