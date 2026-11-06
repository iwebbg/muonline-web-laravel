<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Models\Administrator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Verifies the separate application-owned administrator security boundary.
 */
final class AdminAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Clears the persistent test limiter key before each administrator scenario.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $limiterKey = md5('admin-loginadmin@example.com|127.0.0.1');

        RateLimiter::clear($limiterKey);
    }

    /**
     * Confirms that guests can open the separate administrator login screen.
     */
    public function test_guest_can_open_administrator_login_screen(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee('Verify administrator identity');
    }

    /**
     * Confirms that unauthenticated visitors are redirected to the administrator login.
     */
    public function test_guest_is_redirected_from_administration_to_administrator_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    /**
     * Confirms that a valid game account session never grants administrator access.
     */
    public function test_game_account_cannot_access_administration(): void
    {
        $gameAccount = new GameAccount(
            guid: 1,
            username: 'player',
            displayName: 'Player',
            email: null,
            blocked: false,
            vip: false,
            password: 'secret1',
        );

        $response = $this
            ->actingAs($gameAccount)
            ->get('/admin');

        $response->assertRedirect('/admin/login');
    }

    /**
     * Confirms that an active administrator can authenticate with a hashed password.
     */
    public function test_active_administrator_can_authenticate(): void
    {
        $administrator = Administrator::factory()->create([
            'email' => 'admin@example.com',
        ]);

        $response = $this->postWithCsrf('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'Strong!Password123',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs($administrator, 'admin');
        $this->assertNotNull($administrator->fresh()?->last_login_at);
    }

    /**
     * Confirms that inactive administrator identities cannot create a session.
     */
    public function test_inactive_administrator_cannot_authenticate(): void
    {
        Administrator::factory()
            ->inactive()
            ->create([
                'email' => 'admin@example.com',
            ]);

        $this->from('/admin/login');

        $response = $this->postWithCsrf('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'Strong!Password123',
        ]);

        $response->assertRedirect('/admin/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    /**
     * Confirms that the protected dashboard escapes administrator display names.
     */
    public function test_administrator_dashboard_escapes_identity_name(): void
    {
        $dangerousName = '<script>alert("admin")</script>';
        $administrator = Administrator::factory()->create([
            'name' => $dangerousName,
        ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin');

        $response->assertOk();
        $response->assertSee('Server snapshot');
        $response->assertSee('Characters by class');
        $response->assertSee('Characters by map');
        $response->assertSee('1,550');
        $response->assertSee('Blade Knight');
        $response->assertSee('Lorencia');
        $response->assertSeeText($dangerousName);
        $response->assertDontSee($dangerousName, false);
    }

    /**
     * Confirms the shared header displays the administrator and uses its isolated logout route.
     */
    public function test_administrator_header_uses_administrator_identity_and_logout(): void
    {
        $administrator = Administrator::factory()->create([
            'name' => 'Server Owner',
        ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin');

        $response
            ->assertOk()
            ->assertSee('Server Owner')
            ->assertSee('action="'.route('admin.logout').'"', false)
            ->assertDontSee('action="'.route('logout').'"', false);
    }

    /**
     * Confirms that administrator logout destroys the privileged browser session.
     */
    public function test_administrator_can_log_out(): void
    {
        $administrator = Administrator::factory()->create();

        $this->actingAs($administrator, 'admin');

        $response = $this->postWithCsrf('/admin/logout');

        $response->assertRedirect('/admin/login');
        $this->assertGuest('admin');
    }

    /**
     * Confirms that repeated administrator credential guessing is rate limited.
     */
    public function test_administrator_login_is_rate_limited_after_five_attempts(): void
    {
        Administrator::factory()->create([
            'email' => 'admin@example.com',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->postWithCsrf('/admin/login', [
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ]);

            $response->assertRedirect();
        }

        $blockedResponse = $this->postWithCsrf('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $blockedResponse->assertTooManyRequests();
        $this->assertGuest('admin');
    }

    /**
     * Sends one state-changing administrator request with a valid CSRF token.
     *
     * @param  array<string, mixed>  $data
     */
    private function postWithCsrf(string $uri, array $data = []): TestResponse
    {
        $csrfToken = 'administrator-authentication-test-token';
        $payload = [
            ...$data,
            '_token' => $csrfToken,
        ];

        return $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->post($uri, $payload);
    }
}
