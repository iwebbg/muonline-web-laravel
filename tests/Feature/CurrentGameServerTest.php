<?php

namespace Tests\Feature;

use App\Models\GameServer;
use App\Servers\CurrentGameServer;
use Database\Seeders\GameServerSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the shared current-server metadata source used by public copy.
 */
final class CurrentGameServerTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms administrator-managed values override profile setup defaults.
     */
    public function test_resolves_the_administrator_managed_current_server(): void
    {
        GameServer::factory()->current()->create([
            'name' => 'DreamMu Season 6',
            'version' => 'Season 6',
        ]);

        $currentGameServer = $this->app->make(CurrentGameServer::class);

        $this->assertSame('DreamMu Season 6', $currentGameServer->name());
        $this->assertSame('Season 6', $currentGameServer->version());
    }

    /**
     * Confirms fresh installations remain renderable before server data exists.
     */
    public function test_falls_back_to_the_active_profile_without_a_current_server(): void
    {
        config()->set('game.profile.name', 'Configured server');
        config()->set('game.profile.version', 'Configured version');

        $currentGameServer = $this->app->make(CurrentGameServer::class);

        $this->assertSame('Configured server', $currentGameServer->name());
        $this->assertSame('Configured version', $currentGameServer->version());
    }

    /**
     * Confirms fresh installations seed the selected profile instead of 97d copy.
     */
    public function test_seeds_the_current_server_from_the_active_profile(): void
    {
        config()->set('game.profile.name', 'MuOnline MuEMU Season 6');
        config()->set('game.profile.version', 'Season 6 Episode 3 Update 32');
        config()->set('game.profile.experience', '9999x');
        config()->set('game.profile.drop', '60%');
        config()->set('game.profile.capacity', 500);

        $this->seed(GameServerSeeder::class);

        $this->assertDatabaseHas('game_servers', [
            'is_current' => true,
            'name' => 'MuOnline MuEMU Season 6',
            'version' => 'Season 6 Episode 3 Update 32',
        ]);
        $this->assertDatabaseMissing('game_servers', [
            'version' => '97d + 99i',
        ]);
    }
}
