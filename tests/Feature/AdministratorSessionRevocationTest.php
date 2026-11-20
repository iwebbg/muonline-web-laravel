<?php

namespace Tests\Feature;

use App\Admin\ManageAdministratorIdentity;
use App\Models\Administrator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies existing administrator sessions lose access after identity revocation.
 */
final class AdministratorSessionRevocationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Rejects a logged-in administrator immediately after CLI deactivation.
     */
    public function test_deactivation_revokes_an_existing_session(): void
    {
        Administrator::factory()->create();
        $administrator = Administrator::factory()->create();
        $this->login($administrator);
        $this->app->make(ManageAdministratorIdentity::class)->handle($administrator->id, ['is_active' => false]);

        $this->get('/admin/support')->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
    }

    /**
     * Rejects the old authenticated session after a password reset in another process.
     */
    public function test_password_rotation_revokes_an_existing_session(): void
    {
        $administrator = Administrator::factory()->create();
        $this->login($administrator);
        $this->app->make(ManageAdministratorIdentity::class)->handle($administrator->id, ['password' => 'Rotated!Password123']);

        $this->getJson('/admin/support')->assertUnauthorized();

        $this->assertGuest('admin');
    }

    /**
     * Requires reauthentication for a legacy session without a captured credential hash.
     */
    public function test_legacy_unversioned_session_requires_fresh_login(): void
    {
        $administrator = Administrator::factory()->create();
        $this->login($administrator);

        $this->withSession(['password_hash_admin' => null])->getJson('/admin/support')->assertUnauthorized();

        $this->assertGuest('admin');
    }

    /**
     * Keeps a valid session usable after an ordinary display-name edit.
     */
    public function test_name_change_does_not_revoke_an_active_session(): void
    {
        $administrator = Administrator::factory()->create();
        $this->login($administrator);
        $this->app->make(ManageAdministratorIdentity::class)->handle($administrator->id, ['name' => 'Updated Owner']);

        $this->get('/admin/support')->assertOk()->assertSee('Updated Owner');
    }

    /**
     * Creates a real guard session through the existing CSRF-protected login endpoint.
     */
    private function login(Administrator $administrator): void
    {
        $token = 'administrator-session-audit';
        $this->withSession(['_token' => $token])->post('/admin/login', [
            '_token' => $token,
            'email' => $administrator->email,
            'password' => 'Strong!Password123',
        ])->assertRedirect('/admin');
    }
}
