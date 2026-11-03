<?php

namespace App\Servers;

use App\Models\GameServer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads and caches the standard public status endpoint of an external website.
 */
final class RemoteGameServerStatusClient
{
    /**
     * Returns one cached remote status without allowing redirects or long waits.
     */
    public function status(GameServer $server): RemoteGameServerStatus
    {
        if ($server->website_url === null || $server->website_url === '') {
            return RemoteGameServerStatus::unavailable();
        }

        $cacheKey = $this->cacheKey($server);
        $cacheSeconds = (int) config('game.server_directory.status_cache_seconds', 600);

        $cachedStatus = Cache::remember(
            $cacheKey,
            $cacheSeconds,
            fn (): array => $this->request($server->website_url)->toArray(),
        );

        if (! is_array($cachedStatus)) {
            return RemoteGameServerStatus::unavailable();
        }

        return RemoteGameServerStatus::fromArray($cachedStatus);
    }

    /**
     * Requests and validates the fixed `/server/status` JSON contract.
     */
    private function request(string $websiteUrl): RemoteGameServerStatus
    {
        $statusUrl = rtrim($websiteUrl, '/').'/server/status';

        try {
            $response = Http::acceptJson()
                ->connectTimeout((float) config('game.server_directory.status_connect_timeout_seconds', 1.0))
                ->timeout((float) config('game.server_directory.status_timeout_seconds', 2.0))
                ->withOptions(['allow_redirects' => false])
                ->get($statusUrl);
        } catch (Throwable) {
            return RemoteGameServerStatus::unavailable();
        }

        if (! $response->successful()) {
            return RemoteGameServerStatus::unavailable();
        }

        $online = $response->json('online');
        $onlineUsers = $response->json('online_users');

        if (! is_bool($online) || ! is_int($onlineUsers) || $onlineUsers < 0) {
            return RemoteGameServerStatus::unavailable();
        }

        return new RemoteGameServerStatus(
            reachable: true,
            online: $online,
            onlineUsers: $onlineUsers,
        );
    }

    /**
     * Creates a cache identity that changes when the destination is edited.
     */
    private function cacheKey(GameServer $server): string
    {
        $version = $server->updated_at?->getTimestamp() ?? 0;

        return 'server-directory:remote-status:v2:'.$server->getKey().':'.$version;
    }
}
