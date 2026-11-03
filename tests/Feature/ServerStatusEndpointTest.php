<?php

namespace Tests\Feature;

use App\Game\Contracts\GameServerStatus;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Data\RealmOverview;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Verifies the minimal public status contract and its shared cache boundary.
 */
final class ServerStatusEndpointTest extends TestCase
{
    /**
     * Confirms repeated requests reuse one ten-minute status snapshot.
     */
    public function test_public_server_status_is_cached_and_contains_no_private_data(): void
    {
        Cache::flush();
        $overview = new RealmOverview(
            name: 'DreamMu',
            version: '97d + 99i',
            experience: '9999x',
            drop: '60%',
            online: 42,
            capacity: 500,
            accounts: 100,
            characters: 120,
            guilds: 8,
            dataSource: 'fixture',
        );

        $this->mock(GameServerStatus::class, function (MockInterface $mock): void {
            $mock->shouldReceive('isOnline')->once()->andReturnTrue();
        });
        $this->mock(RealmReadRepository::class, function (MockInterface $mock) use ($overview): void {
            $mock->shouldReceive('overview')->once()->andReturn($overview);
        });

        $firstResponse = $this->getJson(route('server.status'));
        $secondResponse = $this->getJson(route('server.status'));

        $firstResponse
            ->assertOk()
            ->assertJsonPath('online', true)
            ->assertJsonPath('online_users', 42)
            ->assertJsonMissingPath('accounts')
            ->assertJsonMissingPath('characters')
            ->assertJsonStructure(['online', 'online_users', 'generated_at']);
        $secondResponse
            ->assertOk()
            ->assertExactJson($firstResponse->json());
        $this->assertStringContainsString(
            'max-age=600',
            (string) $firstResponse->headers->get('Cache-Control'),
        );
    }
}
