<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Admin\Authorization\AdministratorRole;
use App\Models\Administrator;
use App\Models\AdministratorIdentityRevision;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verifies full-administrator-only Game Master identity lifecycle management.
 */
final class AdminGameMasterManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms Game Masters cannot cross the full-administrator identity boundary.
     */
    public function test_game_master_cannot_manage_other_game_masters(): void
    {
        $gameMaster = Administrator::factory()
            ->gameMaster(AdministratorPermission::cases())
            ->create();

        $this->actingAs($gameMaster, 'admin')
            ->get('/admin/game-masters')
            ->assertForbidden();
    }

    /**
     * Confirms a full administrator creates a hashed, permission-scoped identity.
     */
    public function test_full_administrator_creates_audited_game_master(): void
    {
        $administrator = Administrator::factory()->create();
        $password = 'VeryStrong!Password123';
        $csrfToken = 'game-master-create-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post('/admin/game-masters', [
                '_token' => $csrfToken,
                'name' => 'Senior GM',
                'email' => 'senior-gm@example.com',
                'password' => $password,
                'password_confirmation' => $password,
                'permissions' => [
                    AdministratorPermission::ModerateAccounts->value,
                    AdministratorPermission::ViewAuditLog->value,
                ],
            ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        $gameMaster = Administrator::query()
            ->where('email', 'senior-gm@example.com')
            ->firstOrFail();
        $revision = AdministratorIdentityRevision::query()->sole();

        $this->assertSame(AdministratorRole::GameMaster, $gameMaster->role);
        $this->assertTrue(Hash::check($password, $gameMaster->password));
        $this->assertSame([
            AdministratorPermission::ModerateAccounts->value,
            AdministratorPermission::ViewAuditLog->value,
        ], $gameMaster->permissions);
        $this->assertSame('created', $revision->action);
        $this->assertNull($revision->before_snapshot);
        $this->assertStringNotContainsString($password, (string) json_encode($revision->after_snapshot));
        $this->assertArrayNotHasKey('password', $revision->after_snapshot);
    }

    /**
     * Confirms updates can revoke login and rotate passwords without auditing secrets.
     */
    public function test_full_administrator_updates_and_deactivates_game_master(): void
    {
        $administrator = Administrator::factory()->create();
        $gameMaster = Administrator::factory()
            ->gameMaster([AdministratorPermission::ModerateAccounts])
            ->create();
        $newPassword = 'Rotated!Password456';
        $csrfToken = 'game-master-update-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->put('/admin/game-masters/'.$gameMaster->getKey(), [
                '_token' => $csrfToken,
                'name' => 'Restricted GM',
                'email' => $gameMaster->email,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
                'is_active' => false,
                'permissions' => [AdministratorPermission::ViewAuditLog->value],
            ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        $gameMaster->refresh();
        $revision = AdministratorIdentityRevision::query()->sole();

        $this->assertSame('Restricted GM', $gameMaster->name);
        $this->assertFalse($gameMaster->is_active);
        $this->assertSame([AdministratorPermission::ViewAuditLog->value], $gameMaster->permissions);
        $this->assertTrue(Hash::check($newPassword, $gameMaster->password));
        $this->assertSame('updated_with_password_rotation', $revision->action);
        $this->assertStringNotContainsString($newPassword, (string) json_encode([
            $revision->before_snapshot,
            $revision->after_snapshot,
        ]));
    }

    /**
     * Confirms the GM endpoint cannot mutate a full administrator by URL tampering.
     */
    public function test_game_master_update_rejects_full_administrator_target(): void
    {
        $administrator = Administrator::factory()->create();
        $otherAdministrator = Administrator::factory()->create();
        $csrfToken = 'game-master-target-boundary-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->put('/admin/game-masters/'.$otherAdministrator->getKey(), [
                '_token' => $csrfToken,
                'name' => 'Tampered Administrator',
                'email' => $otherAdministrator->email,
                'password' => null,
                'password_confirmation' => null,
                'is_active' => false,
                'permissions' => [AdministratorPermission::ManageSettings->value],
            ]);

        $response->assertNotFound();
        $this->assertTrue($otherAdministrator->fresh()->is_active);
        $this->assertSame(0, AdministratorIdentityRevision::query()->count());
    }

    /**
     * Confirms the full-administrator page renders only password-free payloads.
     */
    public function test_game_master_manager_never_renders_password_hashes(): void
    {
        $administrator = Administrator::factory()->create();
        $gameMaster = Administrator::factory()
            ->gameMaster([AdministratorPermission::ViewAuditLog])
            ->create();

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/game-masters');

        $response
            ->assertOk()
            ->assertSee('gameMasterManager(', false)
            ->assertSee($gameMaster->email)
            ->assertDontSee($gameMaster->password);
    }
}
