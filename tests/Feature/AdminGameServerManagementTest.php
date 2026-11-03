<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use App\Models\GameServer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies permission-scoped server directory management and current-server safety.
 */
final class AdminGameServerManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms only setting managers can open and mutate the directory.
     */
    public function test_server_manager_requires_settings_permission(): void
    {
        $settingsManager = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageSettings])
            ->create();
        $moderator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ModerateAccounts])
            ->create();

        $this->actingAs($settingsManager, 'admin')
            ->get(route('admin.game-servers.index'))
            ->assertOk()
            ->assertSee('gameServerManager(', false);
        $this->actingAs($moderator, 'admin')
            ->get(route('admin.game-servers.index'))
            ->assertForbidden();
    }

    /**
     * Confirms selecting a new current destination atomically clears the old flag.
     */
    public function test_administrator_creates_new_current_server_with_single_current_flag(): void
    {
        $administrator = Administrator::factory()->create();
        $oldCurrent = GameServer::factory()->current()->create();
        $csrfToken = 'server-create-token';

        $response = $this->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post(route('admin.game-servers.store'), $this->serverPayload([
                '_token' => $csrfToken,
                'slug' => 'dreammu-new-current',
                'name' => 'DreamMu New Current',
                'is_current' => '1',
                'website_url' => null,
            ]));

        $response->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse($oldCurrent->refresh()->is_current);
        $this->assertFalse($oldCurrent->is_visible);
        $this->assertSame(1, GameServer::query()->where('is_current', true)->count());
        $this->assertTrue(GameServer::query()->where('slug', 'dreammu-new-current')->firstOrFail()->is_current);
    }

    /**
     * Confirms external destinations require a valid URL and bounded counters.
     */
    public function test_external_server_validation_rejects_missing_url_and_impossible_online_count(): void
    {
        $administrator = Administrator::factory()->create();
        GameServer::factory()->current()->create();
        $csrfToken = 'server-validation-token';

        $response = $this->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post(route('admin.game-servers.store'), $this->serverPayload([
                '_token' => $csrfToken,
                'website_url' => null,
                'online_count' => 501,
                'capacity' => 500,
            ]));

        $response->assertSessionHasErrors(['website_url', 'online_count']);
    }

    /**
     * Confirms the current website cannot be deleted from its own directory.
     */
    public function test_administrator_cannot_delete_current_server(): void
    {
        $administrator = Administrator::factory()->create();
        $current = GameServer::factory()->current()->create();
        $csrfToken = 'server-delete-token';

        $response = $this->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->delete(route('admin.game-servers.destroy', $current), [
                '_token' => $csrfToken,
            ]);

        $response->assertRedirect()->assertSessionHasErrors('server');
        $this->assertDatabaseHas('game_servers', ['id' => $current->getKey()]);
    }

    /**
     * Returns one complete valid external server payload.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function serverPayload(array $overrides = []): array
    {
        return [
            'slug' => 'dreammu-hard',
            'name' => 'DreamMu Hard',
            'version' => '1.0M',
            'experience' => '100x',
            'drop' => '35%',
            'capacity' => 500,
            'online_count' => 184,
            'is_online' => '1',
            'is_current' => '0',
            'is_visible' => '1',
            'website_url' => 'https://hard.dreammu.example',
            'sort_order' => 10,
            ...$overrides,
        ];
    }
}
