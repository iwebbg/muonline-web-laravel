<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Admin\Authorization\AdministratorRole;
use App\Models\Administrator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the separate administrator and granular Game Master boundaries.
 */
final class AdministratorPermissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms full administrators implicitly own every current capability.
     */
    public function test_administrator_role_has_every_permission(): void
    {
        $administrator = Administrator::factory()->create();

        $this->assertSame(AdministratorRole::Administrator, $administrator->role);

        foreach (AdministratorPermission::cases() as $permission) {
            $this->assertTrue($administrator->hasPermission($permission));
        }
    }

    /**
     * Confirms an audit-only Game Master is redirected to and limited to that area.
     */
    public function test_audit_game_master_can_only_access_audit_area(): void
    {
        $gameMaster = Administrator::factory()
            ->gameMaster([
                AdministratorPermission::ViewAuditLog,
            ])
            ->create();

        $dashboardResponse = $this
            ->actingAs($gameMaster, 'admin')
            ->get('/admin');
        $auditResponse = $this
            ->actingAs($gameMaster, 'admin')
            ->get('/admin/audit-log');
        $csrfToken = 'game-master-permission-test-token';
        $settingsResponse = $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->actingAs($gameMaster, 'admin')
            ->putJson('/admin/character-rules/reset', [
                '_token' => $csrfToken,
            ]);

        $dashboardResponse->assertRedirect('/admin/audit-log');
        $auditResponse
            ->assertOk()
            ->assertSee('Web Control')
            ->assertSee('Audit log')
            ->assertDontSee('Game servers');
        $settingsResponse->assertForbidden();
    }

    /**
     * Confirms settings-only Game Masters cannot read the immutable audit trail.
     */
    public function test_settings_game_master_cannot_access_audit_log(): void
    {
        $gameMaster = Administrator::factory()
            ->gameMaster([
                AdministratorPermission::ManageSettings,
            ])
            ->create();

        $dashboardResponse = $this
            ->actingAs($gameMaster, 'admin')
            ->get('/admin');
        $auditResponse = $this
            ->actingAs($gameMaster, 'admin')
            ->get('/admin/audit-log');

        $dashboardResponse
            ->assertOk()
            ->assertSee('Web Control')
            ->assertSee('Game servers')
            ->assertDontSee('Audit log');
        $auditResponse->assertForbidden();
    }

    /**
     * Confirms a Game Master without capabilities cannot enter a privileged area.
     */
    public function test_game_master_without_permissions_is_forbidden(): void
    {
        $gameMaster = Administrator::factory()
            ->gameMaster()
            ->create();

        $dashboardResponse = $this
            ->actingAs($gameMaster, 'admin')
            ->get('/admin');
        $auditResponse = $this
            ->actingAs($gameMaster, 'admin')
            ->get('/admin/audit-log');

        $dashboardResponse->assertForbidden();
        $auditResponse->assertForbidden();
    }
}
