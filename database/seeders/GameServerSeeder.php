<?php

namespace Database\Seeders;

use App\Models\GameServer;
use Illuminate\Database\Seeder;

/**
 * Creates an editable current server and one external directory example.
 */
final class GameServerSeeder extends Seeder
{
    /**
     * Seeds idempotent server-directory examples for a fresh installation.
     */
    public function run(): void
    {
        GameServer::query()->updateOrCreate(
            ['slug' => 'dreammu-97d99i-easy'],
            [
                'name' => 'DreamMu 97d+99i Easy',
                'version' => '97d + 99i',
                'experience' => '9999x',
                'drop' => '60%',
                'capacity' => 500,
                'show_online_status' => true,
                'show_online_users' => true,
                'is_current' => true,
                'is_visible' => true,
                'website_url' => null,
                'sort_order' => 0,
            ],
        );

        GameServer::query()->updateOrCreate(
            ['slug' => 'dreammu-1m-hard'],
            [
                'name' => 'DreamMu 1.0M Hard',
                'version' => '1.0M',
                'experience' => '100x',
                'drop' => '35%',
                'capacity' => 1000,
                'show_online_status' => true,
                'show_online_users' => true,
                'is_current' => false,
                'is_visible' => true,
                'website_url' => 'https://hard.dreammu.example',
                'sort_order' => 10,
            ],
        );
    }
}
