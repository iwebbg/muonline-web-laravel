<?php

namespace Tests\Feature;

use App\Models\GameServer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
            'online_count' => 184,
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
        GameServer::factory()->current()->create();
        GameServer::factory()->create([
            'name' => 'Hidden development server',
            'is_visible' => false,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Hidden development server');
    }
}
