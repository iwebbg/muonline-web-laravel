<?php

namespace Tests\Feature;

use App\Models\GameServer;
use App\Servers\RemoteGameServerStatusClient;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verifies external status validation, failure isolation and request caching.
 */
final class RemoteGameServerStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms one valid remote response is reused for ten minutes.
     */
    public function test_remote_status_is_validated_and_cached(): void
    {
        Cache::flush();
        Http::fake([
            'https://hard.dreammu.example/server/status' => Http::response([
                'online' => true,
                'online_users' => 184,
                'generated_at' => now()->toAtomString(),
            ]),
        ]);
        $server = GameServer::factory()->create([
            'website_url' => 'https://hard.dreammu.example',
        ]);
        $client = app(RemoteGameServerStatusClient::class);

        $firstStatus = $client->status($server);
        $secondStatus = $client->status($server);

        $this->assertTrue($firstStatus->reachable);
        $this->assertTrue($firstStatus->online);
        $this->assertSame(184, $firstStatus->onlineUsers);
        $this->assertEquals($firstStatus, $secondStatus);
        Http::assertSentCount(1);
    }

    /**
     * Confirms malformed responses become cached unavailable states.
     */
    public function test_invalid_remote_status_does_not_break_the_directory(): void
    {
        Cache::flush();
        Http::fake([
            'https://broken.dreammu.example/server/status' => Http::response([
                'online' => 'yes',
                'online_users' => -5,
            ]),
        ]);
        $server = GameServer::factory()->create([
            'website_url' => 'https://broken.dreammu.example',
        ]);
        $client = app(RemoteGameServerStatusClient::class);

        $status = $client->status($server);

        $this->assertFalse($status->reachable);
        $this->assertNull($status->online);
        $this->assertNull($status->onlineUsers);
    }
}
