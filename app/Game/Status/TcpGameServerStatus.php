<?php

namespace App\Game\Status;

use App\Game\Contracts\GameServerStatus;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Probes the player-facing game server port independently from SQL availability.
 */
final readonly class TcpGameServerStatus implements GameServerStatus
{
    /**
     * Creates the status reader with a short-lived shared result cache.
     */
    public function __construct(
        private CacheRepository $cache,
    ) {}

    /**
     * Returns the cached reachability of the configured game server TCP endpoint.
     */
    public function isOnline(): bool
    {
        $host = trim((string) config('game.server_status.host'));
        $port = (int) config('game.server_status.port');
        $timeoutSeconds = (float) config('game.server_status.timeout_seconds');
        $cacheSeconds = max(1, (int) config('game.server_status.cache_seconds'));

        if ($host === '' || $port < 1 || $port > 65_535 || $timeoutSeconds <= 0) {
            return false;
        }

        $cacheKey = 'game-server-status:'.hash('sha256', $host.':'.$port);

        return (bool) $this->cache->remember(
            $cacheKey,
            $cacheSeconds,
            fn (): bool => $this->probe($host, $port, $timeoutSeconds),
        );
    }

    /**
     * Attempts one bounded TCP connection and closes it immediately after success.
     */
    private function probe(string $host, int $port, float $timeoutSeconds): bool
    {
        $endpoint = sprintf('tcp://%s:%d', $host, $port);
        $errorCode = 0;
        $errorMessage = '';
        $connection = @stream_socket_client(
            $endpoint,
            $errorCode,
            $errorMessage,
            $timeoutSeconds,
            STREAM_CLIENT_CONNECT,
        );

        if (! is_resource($connection)) {
            return false;
        }

        fclose($connection);

        return true;
    }
}
