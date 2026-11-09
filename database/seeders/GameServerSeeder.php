<?php

namespace Database\Seeders;

use App\Models\GameServer;
use Illuminate\Database\Seeder;

/**
 * Creates one editable current server from the selected game profile.
 */
final class GameServerSeeder extends Seeder
{
    /**
     * Seeds idempotent server-directory examples for a fresh installation.
     */
    public function run(): void
    {
        GameServer::query()->firstOrCreate(
            ['is_current' => true],
            [
                'slug' => 'current-server',
                'name' => (string) config('game.profile.name'),
                'version' => (string) config('game.profile.version'),
                'experience' => (string) config('game.profile.experience'),
                'drop' => (string) config('game.profile.drop'),
                'capacity' => (int) config('game.profile.capacity'),
                'show_online_status' => true,
                'show_online_users' => true,
                'is_visible' => true,
                'website_url' => null,
                'sort_order' => 0,
            ],
        );
    }
}
