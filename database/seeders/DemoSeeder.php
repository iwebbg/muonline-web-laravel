<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LogicException;

/**
 * Selects the demo fixture owned by the configured game-version profile.
 */
final class DemoSeeder extends Seeder
{
    /**
     * Seeds only the commerce and identity fixture compatible with the active adapter.
     */
    public function run(): void
    {
        $seeder = match (config('game.profile.key')) {
            'darksteam_97d99i' => Demo97d99iSeeder::class,
            'darksteam_1_0m' => Demo1_0MSeeder::class,
            default => throw new LogicException('No demo seed is available for the configured GAME_SERVER_PROFILE.'),
        };

        $this->call($seeder);
    }
}
