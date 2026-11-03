<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Verifies secure player sessions backed by isolated legacy account fixtures.
 */
final class AuthenticationTest extends TestCase
{
    /**
     * Creates an isolated MEMB_INFO table for authentication requests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.game', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $database = $this->app->make(DatabaseManager::class);

        $database->purge('game');
        $database->connection('game')->statement(
            'CREATE TABLE MEMB_INFO (memb_guid INTEGER PRIMARY KEY, memb___id TEXT NOT NULL, memb__pwd TEXT NOT NULL, memb_name TEXT NOT NULL, mail_addr TEXT NULL, bloc_code TEXT NOT NULL, IsVip INTEGER NOT NULL)',
        );

        $limiterKey = md5('logintestuser|127.0.0.1');

        RateLimiter::clear($limiterKey);
    }

    /**
     * Confirms that guests can open the player login screen.
     */
    public function test_guest_can_open_login_screen(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Enter your account');
        $response->assertSee('Game identity');
        $response->assertDontSee('MEMB_INFO');
    }

    /**
     * Confirms that a guest cannot open the private account dashboard.
     */
    public function test_guest_is_redirected_from_account_dashboard_to_login(): void
    {
        $response = $this->get('/account');

        $response->assertRedirect('/login');
    }

    /**
     * Confirms that valid plaintext legacy credentials create a player session.
     */
    public function test_player_can_authenticate_with_valid_legacy_credentials(): void
    {
        $this->insertAccount();

        $response = $this->postWithCsrf('/login', [
            'username' => 'testuser',
            'password' => 'secret1',
        ]);

        $response->assertRedirect('/account');
        $this->assertAuthenticated();
        $this->assertSame('testuser', auth()->user()?->getAuthIdentifier());
    }

    /**
     * Confirms that an invalid password returns one generic authentication error.
     */
    public function test_player_cannot_authenticate_with_invalid_password(): void
    {
        $this->insertAccount();

        $this->from('/login');

        $response = $this->postWithCsrf('/login', [
            'username' => 'testuser',
            'password' => 'wrong',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    /**
     * Confirms that a blocked legacy account cannot create a web session.
     */
    public function test_blocked_player_cannot_authenticate(): void
    {
        $this->insertAccount(blocked: true);

        $this->from('/login');

        $response = $this->postWithCsrf('/login', [
            'username' => 'testuser',
            'password' => 'secret1',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    /**
     * Confirms that authentication input respects the legacy ten-character limit.
     */
    public function test_login_rejects_credentials_longer_than_legacy_columns(): void
    {
        $this->from('/login');

        $response = $this->postWithCsrf('/login', [
            'username' => 'username-too-long',
            'password' => 'password-too-long',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'username',
            'password',
        ]);
        $this->assertGuest();
    }

    /**
     * Confirms that repeated credential guessing is blocked on the sixth attempt.
     */
    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $this->insertAccount();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->postWithCsrf('/login', [
                'username' => 'testuser',
                'password' => 'wrong',
            ]);

            $response->assertRedirect();
        }

        $blockedResponse = $this->postWithCsrf('/login', [
            'username' => 'testuser',
            'password' => 'wrong',
        ]);

        $blockedResponse->assertTooManyRequests();
        $this->assertGuest();
    }

    /**
     * Confirms that logout invalidates the authenticated player session.
     */
    public function test_authenticated_player_can_log_out(): void
    {
        $account = new GameAccount(
            guid: 1,
            username: 'testuser',
            displayName: 'Test User',
            email: null,
            blocked: false,
            vip: false,
            password: 'secret1',
        );

        $this->actingAs($account);

        $response = $this->postWithCsrf('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    /**
     * Inserts one deterministic legacy account fixture.
     */
    private function insertAccount(bool $blocked = false): void
    {
        $database = $this->app->make(DatabaseManager::class);

        $database->connection('game')->table('MEMB_INFO')->insert([
            'memb_guid' => 1,
            'memb___id' => 'testuser',
            'memb__pwd' => 'secret1',
            'memb_name' => 'Test User',
            'mail_addr' => 'player@example.com',
            'bloc_code' => $blocked ? '1' : '0',
            'IsVip' => 0,
        ]);
    }

    /**
     * Sends one state-changing request with the same CSRF boundary as the browser.
     *
     * @param  array<string, mixed>  $data
     */
    private function postWithCsrf(string $uri, array $data = []): TestResponse
    {
        $csrfToken = 'authentication-test-token';
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
