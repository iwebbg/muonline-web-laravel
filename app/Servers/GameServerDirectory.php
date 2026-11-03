<?php

namespace App\Servers;

use App\Game\Data\RealmOverview;
use App\Models\GameServer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Builds the public server directory around one locally managed game server.
 */
final class GameServerDirectory
{
    /**
     * Combines live local metrics with administrator-managed server metadata.
     *
     * @return array{current: array<string, mixed>, entries: list<array<string, mixed>>}
     */
    public function publicData(
        RealmOverview $liveOverview,
        bool $currentServerOnline,
    ): array {
        if (! Schema::hasTable('game_servers')) {
            return $this->fallbackData($liveOverview, $currentServerOnline);
        }

        $servers = GameServer::query()
            ->where('is_visible', true)
            ->orderByDesc('is_current')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $currentServer = $servers->firstWhere('is_current', true);

        if (! $currentServer instanceof GameServer) {
            return $this->fallbackData($liveOverview, $currentServerOnline);
        }

        $current = $this->serializeCurrent(
            server: $currentServer,
            liveOverview: $liveOverview,
            currentServerOnline: $currentServerOnline,
        );
        $entries = $this->serializeEntries(
            servers: $servers,
            liveOverview: $liveOverview,
            currentServerOnline: $currentServerOnline,
        );

        return [
            'current' => $current,
            'entries' => $entries,
        ];
    }

    /**
     * Serializes every visible destination in stable administrator order.
     *
     * @param  Collection<int, GameServer>  $servers
     * @return list<array<string, mixed>>
     */
    private function serializeEntries(
        Collection $servers,
        RealmOverview $liveOverview,
        bool $currentServerOnline,
    ): array {
        $entries = [];

        foreach ($servers as $server) {
            $entries[] = $server->is_current
                ? $this->serializeCurrent($server, $liveOverview, $currentServerOnline)
                : $this->serializeExternal($server);
        }

        return $entries;
    }

    /**
     * Applies live database counters to the locally managed directory entry.
     *
     * @return array<string, mixed>
     */
    private function serializeCurrent(
        GameServer $server,
        RealmOverview $liveOverview,
        bool $currentServerOnline,
    ): array {
        $live = $liveOverview->toArray();

        return [
            ...$live,
            'id' => $server->getKey(),
            'slug' => $server->slug,
            'name' => $server->name,
            'version' => $server->version,
            'experience' => $server->experience,
            'drop' => $server->drop,
            'capacity' => $server->capacity,
            'is_online' => $currentServerOnline,
            'is_current' => true,
            'url' => route('home'),
        ];
    }

    /**
     * Serializes one manually maintained external server destination.
     *
     * @return array<string, mixed>
     */
    private function serializeExternal(GameServer $server): array
    {
        return [
            'id' => $server->getKey(),
            'slug' => $server->slug,
            'name' => $server->name,
            'version' => $server->version,
            'experience' => $server->experience,
            'drop' => $server->drop,
            'online' => $server->online_count,
            'capacity' => $server->capacity,
            'accounts' => null,
            'characters' => null,
            'guilds' => null,
            'data_source' => 'directory',
            'is_online' => $server->is_online,
            'is_current' => false,
            'url' => $server->website_url,
        ];
    }

    /**
     * Keeps the home page available before the directory migration is seeded.
     *
     * @return array{current: array<string, mixed>, entries: list<array<string, mixed>>}
     */
    private function fallbackData(
        RealmOverview $liveOverview,
        bool $currentServerOnline,
    ): array {
        $current = [
            ...$liveOverview->toArray(),
            'id' => 0,
            'slug' => 'current-server',
            'is_online' => $currentServerOnline,
            'is_current' => true,
            'url' => route('home'),
        ];

        return [
            'current' => $current,
            'entries' => [$current],
        ];
    }
}
