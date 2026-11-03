<?php

namespace Tests\Feature;

use App\Models\GameServer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verifies database-managed server metadata and the public destination selector.
 */
final class GameServerDirectoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms current metadata overrides environment defaults on the home page.
     */
    public function test_home_uses_current_database_server_and_lists_external_destination(): void
    {
        Cache::flush();
        Http::fake([
            'https://hard.dreammu.example/server/status' => Http::response([
                'online' => true,
                'online_users' => 184,
                'generated_at' => now()->toAtomString(),
            ]),
        ]);
        GameServer::factory()->current()->create([
            'name' => 'DreamMu 97d+99i Easy',
            'version' => '97d Easy',
            'experience' => '5000x',
            'drop' => '55%',
            'capacity' => 750,
        ]);
        GameServer::factory()->create([
            'name' => 'DreamMu 1.0M Hard',
            'version' => '1.0M',
            'experience' => '100x',
            'drop' => '35%',
            'website_url' => 'https://hard.dreammu.example',
        ]);

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('DreamMu 97d+99i Easy')
            ->assertSee('97d Easy')
            ->assertSee('5000x')
            ->assertSee('55%')
            ->assertSee('DreamMu 1.0M Hard')
            ->assertSee('https://hard.dreammu.example', false)
            ->assertSee('184');
    }

    /**
     * Confirms hidden destinations never appear in the player selector.
     */
    public function test_home_hides_disabled_server_destinations(): void
    {
        Http::fake();
        GameServer::factory()->current()->create();
        GameServer::factory()->create([
            'name' => 'Hidden development server',
            'is_visible' => false,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Hidden development server');
    }

    /**
     * Confirms visibility flags suppress remote status fields in the selector.
     */
    public function test_home_honors_status_and_user_visibility_flags(): void
    {
        Cache::flush();
        Http::fake([
            'https://private.dreammu.example/server/status' => Http::response([
                'online' => true,
                'online_users' => 321,
                'generated_at' => now()->toAtomString(),
            ]),
        ]);
        GameServer::factory()->current()->create();
        GameServer::factory()->create([
            'name' => 'Private counters server',
            'website_url' => 'https://private.dreammu.example',
            'show_online_status' => false,
            'show_online_users' => false,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Private counters server')
            ->assertDontSee('321');

        Http::assertNothingSent();
    }
}
