<?php

namespace Tests\Feature;

use App\Admin\Audit\AuditLogReader;
use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use App\Models\ModuleProfile;
use App\Models\ModuleProfileRevision;
use App\Modules\ServerModule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies audited, permission-scoped module profile administration.
 */
final class AdminModuleProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms guests are redirected to the separate administrator login.
     */
    public function test_guest_is_redirected_from_module_settings(): void
    {
        $response = $this->get(route('admin.modules.index'));

        $response->assertRedirect(route('admin.login'));
    }

    /**
     * Confirms a Game Master without settings permission cannot read modules.
     */
    public function test_game_master_without_settings_permission_is_forbidden(): void
    {
        $gameMaster = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();

        $response = $this
            ->actingAs($gameMaster, 'admin')
            ->get(route('admin.modules.index'));

        $response->assertForbidden();
    }

    /**
     * Confirms the active profile and configured defaults are rendered.
     */
    public function test_settings_administrator_can_view_active_module_profile(): void
    {
        $administrator = Administrator::factory()->create();

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get(route('admin.modules.index'));

        $response
            ->assertOk()
            ->assertSee('darksteam_97d99i')
            ->assertSee('Account registration')
            ->assertSee('Player market');
    }

    /**
     * Confirms one complete update persists and creates an immutable audit event.
     */
    public function test_valid_update_persists_allow_listed_snapshot_and_audit_revision(): void
    {
        $administrator = Administrator::factory()->create();
        $modules = $this->modulePayload();
        $modules[ServerModule::VoteRewards->value] = false;
        $modules['unexpected_module'] = true;
        $csrfToken = 'module-profile-update-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->put(route('admin.modules.update'), [
                '_token' => $csrfToken,
                'revision' => 0,
                'modules' => $modules,
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('status');

        $profile = ModuleProfile::query()->sole();
        $revision = ModuleProfileRevision::query()->sole();

        $this->assertSame(1, $profile->revision);
        $this->assertFalse($profile->modules[ServerModule::VoteRewards->value]);
        $this->assertArrayNotHasKey('unexpected_module', $profile->modules);
        $this->assertSame($administrator->getKey(), $profile->updated_by_administrator_id);
        $this->assertSame($profile->getKey(), $revision->module_profile_id);
        $this->assertTrue($revision->before_snapshot[ServerModule::VoteRewards->value]);
        $this->assertFalse($revision->after_snapshot[ServerModule::VoteRewards->value]);

        $entries = $this->app->make(AuditLogReader::class)->latest(type: 'module_profile');

        $this->assertCount(1, $entries);
        $this->assertSame('module_profile', $entries[0]['type']);
    }

    /**
     * Confirms an outdated form cannot overwrite a newer administrator revision.
     */
    public function test_stale_revision_is_rejected_without_persisting_another_snapshot(): void
    {
        $administrator = Administrator::factory()->create();
        ModuleProfile::query()->create([
            'profile_key' => 'darksteam_97d99i',
            'game_version' => '97d + 99i',
            'modules' => $this->modulePayload(),
            'revision' => 2,
            'updated_by_administrator_id' => $administrator->getKey(),
        ]);
        $csrfToken = 'stale-module-profile-token';

        $response = $this
            ->from(route('admin.modules.index'))
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->put(route('admin.modules.update'), [
                '_token' => $csrfToken,
                'revision' => 1,
                'modules' => [],
            ]);

        $response
            ->assertRedirect(route('admin.modules.index'))
            ->assertSessionHasErrors('modules');
        $this->assertSame(2, ModuleProfile::query()->sole()->revision);
        $this->assertSame(0, ModuleProfileRevision::query()->count());
    }

    /**
     * Confirms auctions cannot be exposed without their market entry point.
     */
    public function test_auction_requires_market_module_to_be_enabled(): void
    {
        $administrator = Administrator::factory()->create();
        $modules = $this->modulePayload();
        $modules[ServerModule::Market->value] = false;
        $csrfToken = 'module-dependency-token';

        $response = $this
            ->from(route('admin.modules.index'))
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->put(route('admin.modules.update'), [
                '_token' => $csrfToken,
                'revision' => 0,
                'modules' => $modules,
            ]);

        $response
            ->assertRedirect(route('admin.modules.index'))
            ->assertSessionHasErrors('modules.auction');
        $this->assertSame(0, ModuleProfile::query()->count());
        $this->assertSame(0, ModuleProfileRevision::query()->count());
    }

    /**
     * Builds a complete enabled module payload for administrator requests.
     *
     * @return array<string, bool>
     */
    private function modulePayload(): array
    {
        $modules = [];

        foreach (ServerModule::cases() as $module) {
            $modules[$module->value] = true;
        }

        return $modules;
    }
}
