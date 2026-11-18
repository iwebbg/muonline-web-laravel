<?php

namespace Tests\Feature;

use App\AccountRegistration\AccountRegistrationBlockReason;
use App\AccountRegistration\AccountRegistrationStatus;
use App\AccountRegistration\Actions\RegisterGameAccount;
use App\AccountRegistration\Exceptions\AccountRegistrationRejected;
use App\Mail\GameAccountActivationMail;
use App\Models\AccountRegistrationOperation;
use App\Models\WebsiteSetting;
use App\Website\WebsiteSettingsManager;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Verifies secure, atomic and audited 97d+99i account registration.
 */
final class AccountRegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Creates isolated legacy account tables and enables test-only game writes.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.writes_enabled', true);
        config()->set('game.account_password_scheme', 'plaintext');
        config()->set('services.recaptcha.enabled', false);
        Mail::fake();
        config()->set('database.connections.game', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
            'readonly' => false,
        ]);

        $database = $this->app->make(DatabaseManager::class);

        $database->purge('game');

        $connection = $database->connection('game');

        $connection->statement(
            'CREATE TABLE MEMB_INFO (memb_guid INTEGER PRIMARY KEY AUTOINCREMENT, memb___id TEXT NOT NULL UNIQUE, memb__pwd TEXT NOT NULL, memb_name TEXT NOT NULL, sno__numb TEXT NOT NULL, mail_addr TEXT NULL, bloc_code TEXT NOT NULL, ctl1_code TEXT NOT NULL, IsVip INTEGER NOT NULL, VipExpirationTime INTEGER NOT NULL)',
        );
        $connection->statement(
            'CREATE TABLE VI_CURR_INFO (ends_days TEXT NULL, chek_code TEXT NOT NULL, used_time INTEGER NULL, memb___id TEXT NOT NULL UNIQUE, memb_name TEXT NOT NULL, memb_guid INTEGER NOT NULL, sno__numb TEXT NOT NULL, Bill_Section INTEGER NULL, Bill_Value INTEGER NULL, Bill_Hour INTEGER NULL, Surplus_Point INTEGER NULL, Surplus_Minute TEXT NULL, Increase_Days INTEGER NULL)',
        );

        RateLimiter::clear(md5('registrationregistration|127.0.0.1'));
        RateLimiter::clear(md5('registrationregistration|203.0.113.10'));
    }

    /**
     * Confirms that guests can open the bilingual account-registration screen.
     */
    public function test_guest_can_open_registration_screen(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSee('Create your account')
            ->assertSeeInOrder([
                'Accounts',
                '1,550',
                'Characters',
                '1,620',
                'Guilds',
                '114',
            ])
            ->assertDontSee('MEMB_INFO')
            ->assertDontSee('VI_CURR_INFO')
            ->assertDontSee('Legacy character limit');
    }

    /**
     * Confirms a valid request creates both required legacy rows and a safe audit.
     */
    public function test_valid_request_creates_complete_game_account_and_audit(): void
    {
        WebsiteSetting::query()->create(['require_email_verification' => true]);
        $response = $this->postRegistration([
            'username' => 'newhero',
            'email' => 'PLAYER@EXAMPLE.COM',
            'password' => 'secret_1',
            'password_confirmation' => 'secret_1',
            'bloc_code' => '1',
        ]);

        $response
            ->assertRedirect('/login')
            ->assertSessionHas('status', 'Your account was created. Check your email to activate it before signing in.');

        $account = $this->gameDatabase()
            ->table('MEMB_INFO')
            ->where('memb___id', 'newhero')
            ->first();
        $billing = $this->gameDatabase()
            ->table('VI_CURR_INFO')
            ->where('memb___id', 'newhero')
            ->first();

        $this->assertNotNull($account);
        $this->assertSame('secret_1', $account->memb__pwd);
        $this->assertSame('player@example.com', $account->mail_addr);
        $this->assertSame('1', $account->bloc_code);
        $this->assertNotNull($billing);
        $this->assertSame($account->memb_guid, $billing->memb_guid);
        $this->assertSame('newhero', $billing->memb_name);

        $operation = AccountRegistrationOperation::query()->sole();
        $serializedAudit = json_encode($operation->toArray(), JSON_THROW_ON_ERROR);

        $this->assertSame(AccountRegistrationStatus::Completed, $operation->status);
        $this->assertSame('newhero', $operation->after_snapshot['account_id']);
        $this->assertTrue($operation->after_snapshot['memb_info_created']);
        $this->assertTrue($operation->after_snapshot['vi_curr_info_created']);
        $this->assertStringNotContainsString('secret_1', $serializedAudit);
        Mail::assertSent(GameAccountActivationMail::class, function (GameAccountActivationMail $mail): bool {
            return $mail->hasTo('player@example.com');
        });
    }

    /**
     * Keeps the default registration usable immediately without sending activation mail.
     */
    public function test_registration_defaults_to_active_accounts_without_email_verification(): void
    {
        $this->postRegistration(['require_email_verification' => true, 'bloc_code' => '1'])
            ->assertRedirect('/login')
            ->assertSessionHas('status', __('ui.registration.completed'));

        $this->assertSame('0', $this->gameDatabase()->table('MEMB_INFO')->value('bloc_code'));
        $operation = AccountRegistrationOperation::query()->sole();
        $this->assertFalse($operation->settings_snapshot['require_email_verification']);
        $this->assertFalse($operation->after_snapshot['require_email_verification']);
        $this->assertDatabaseCount('game_account_activations', 0);
        Mail::assertNothingSent();
    }

    /**
     * Keeps paused registration visible while rejecting HTTP and direct action writes.
     */
    public function test_disabled_registration_cannot_be_bypassed(): void
    {
        WebsiteSetting::query()->create(['registration_enabled' => false]);

        $this->get('/login')->assertOk()->assertSee('href="'.route('register').'"', false);
        $this->get('/register')->assertOk()
            ->assertSee('New registrations are temporarily unavailable.')
            ->assertDontSee('name="username"', false)
            ->assertDontSee('action="'.route('register.store').'"', false);
        $this->postRegistration()->assertRedirect('/register');
        $this->assertDatabaseCount('account_registration_operations', 0);
        $this->assertSame(0, $this->gameDatabase()->table('MEMB_INFO')->count());
        Mail::assertNothingSent();

        try {
            $this->app->make(RegisterGameAccount::class)->handle('bypass', 'secret_1', 'test@example.com');
            $this->fail('Disabled registration must reject direct action calls.');
        } catch (AccountRegistrationRejected $exception) {
            $this->assertSame(AccountRegistrationBlockReason::RegistrationDisabled, $exception->reason);
        }
        $this->assertDatabaseCount('account_registration_operations', 0);
        $this->assertSame(0, $this->gameDatabase()->table('MEMB_INFO')->count());
    }

    /**
     * Switching email verification affects new accounts without unblocking pending accounts.
     */
    public function test_email_policy_changes_leave_existing_pending_accounts_untouched(): void
    {
        $setting = WebsiteSetting::query()->create(['require_email_verification' => true]);
        $this->postRegistration()->assertRedirect('/login');
        $setting->update(['require_email_verification' => false]);
        $this->app->make(WebsiteSettingsManager::class)->forget();
        $this->postRegistration(['username' => 'second'])->assertRedirect('/login');

        $this->assertSame('1', $this->gameDatabase()->table('MEMB_INFO')->where('memb___id', 'newhero')->value('bloc_code'));
        $this->assertSame('0', $this->gameDatabase()->table('MEMB_INFO')->where('memb___id', 'second')->value('bloc_code'));
        Mail::assertSentCount(1);
    }

    /**
     * Confirms invalid legacy credentials are rejected before any database write.
     */
    public function test_invalid_legacy_fields_are_rejected_without_writes(): void
    {
        $this->from('/register');

        $response = $this->postRegistration([
            'username' => 'bad account',
            'email' => 'invalid-email',
            'password' => 'abc',
            'password_confirmation' => 'different',
        ]);

        $response
            ->assertRedirect('/register')
            ->assertSessionHasErrors([
                'username' => 'Use only Latin letters, numbers, hyphens and underscores.',
                'email' => 'Enter a valid email address.',
                'password' => 'The password must contain between 4 and 10 characters.',
            ])
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $this->assertSame(0, $this->gameDatabase()->table('MEMB_INFO')->count());
        $this->assertSame(0, $this->gameDatabase()->table('VI_CURR_INFO')->count());
        $this->assertSame(0, AccountRegistrationOperation::query()->count());
    }

    /**
     * Confirms a matching reCAPTCHA v3 action and score permit registration.
     */
    public function test_recaptcha_v3_accepts_matching_registration_action(): void
    {
        config()->set('services.recaptcha.enabled', true);
        config()->set('services.recaptcha.secret_key', 'test-secret');
        config()->set('services.recaptcha.hostname', 'localhost');
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.9,
                'action' => 'register',
                'hostname' => 'localhost',
            ]),
        ]);

        $response = $this->postRegistration([
            'recaptcha_token' => 'fresh-browser-token',
        ]);

        $response->assertRedirect('/login');
        $this->assertSame(1, $this->gameDatabase()->table('MEMB_INFO')->count());
        Http::assertSent(function ($request): bool {
            return $request['secret'] === 'test-secret'
                && $request['response'] === 'fresh-browser-token'
                && $request['remoteip'] === '127.0.0.1';
        });
    }

    /**
     * Confirms a low score cannot create any legacy account rows.
     */
    public function test_recaptcha_v3_rejects_low_score_without_writes(): void
    {
        config()->set('services.recaptcha.enabled', true);
        config()->set('services.recaptcha.secret_key', 'test-secret');
        Http::fake([
            'https://www.google.com/recaptcha/api/siteverify' => Http::response([
                'success' => true,
                'score' => 0.1,
                'action' => 'register',
            ]),
        ]);

        $response = $this->postRegistration([
            'recaptcha_token' => 'low-score-token',
        ]);

        $response->assertSessionHasErrors('recaptcha_token');
        $this->assertSame(0, $this->gameDatabase()->table('MEMB_INFO')->count());
        $this->assertSame(0, $this->gameDatabase()->table('VI_CURR_INFO')->count());
    }

    /**
     * Confirms a duplicate account is not changed and records a failed attempt.
     */
    public function test_duplicate_account_is_rejected_and_audited(): void
    {
        $this->insertExistingAccount('taken');
        $this->from('/register');

        $response = $this->postRegistration([
            'username' => 'taken',
            'email' => 'second@example.com',
            'password' => 'secret_2',
            'password_confirmation' => 'secret_2',
        ]);

        $response
            ->assertRedirect('/register')
            ->assertSessionHasErrors([
                'username' => 'This account ID is already in use.',
            ]);

        $this->assertSame(1, $this->gameDatabase()->table('MEMB_INFO')->count());
        $this->assertSame(1, $this->gameDatabase()->table('VI_CURR_INFO')->count());

        $operation = AccountRegistrationOperation::query()->sole();

        $this->assertSame(AccountRegistrationStatus::Failed, $operation->status);
        $this->assertSame('account_already_exists', $operation->failure_code);
    }

    /**
     * Confirms the production safety flags prevent game and audit writes.
     */
    public function test_read_only_game_connection_rejects_registration_without_writes(): void
    {
        config()->set('database.connections.game.readonly', true);

        $response = $this->postRegistration();

        $response->assertServiceUnavailable();
        $this->assertSame(0, $this->gameDatabase()->table('MEMB_INFO')->count());
        $this->assertSame(0, $this->gameDatabase()->table('VI_CURR_INFO')->count());
        $this->assertSame(0, AccountRegistrationOperation::query()->count());
    }

    /**
     * Confirms failure of the required billing insert rolls back MEMB_INFO.
     */
    public function test_billing_failure_rolls_back_account_insert_and_marks_audit_failed(): void
    {
        $this->gameDatabase()->statement('DROP TABLE VI_CURR_INFO');

        $response = $this->postRegistration();

        $response->assertServerError();
        $this->assertSame(0, $this->gameDatabase()->table('MEMB_INFO')->count());

        $operation = AccountRegistrationOperation::query()->sole();

        $this->assertSame(AccountRegistrationStatus::Failed, $operation->status);
        $this->assertSame('unexpected_error', $operation->failure_code);
    }

    /**
     * Confirms the public endpoint blocks the eleventh registration attempt per hour.
     */
    public function test_registration_is_rate_limited_after_ten_attempts(): void
    {
        $csrfToken = 'registration-rate-limit-token';
        $serverVariables = [
            'REMOTE_ADDR' => '203.0.113.10',
        ];
        $payload = [
            'username' => 'bad account',
            'email' => 'invalid-email',
            'password' => 'abc',
            'password_confirmation' => 'different',
            '_token' => $csrfToken,
        ];

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $response = $this
                ->withServerVariables($serverVariables)
                ->withSession([
                    '_token' => $csrfToken,
                ])
                ->post('/register', $payload);

            $response->assertRedirect();
        }

        $blockedResponse = $this
            ->withServerVariables($serverVariables)
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->post('/register', $payload);

        $blockedResponse->assertTooManyRequests();
    }

    /**
     * Sends one registration request through the browser CSRF boundary.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function postRegistration(array $overrides = []): TestResponse
    {
        $csrfToken = 'registration-test-token';
        $payload = [
            'username' => 'newhero',
            'email' => 'player@example.com',
            'password' => 'secret_1',
            'password_confirmation' => 'secret_1',
            ...$overrides,
            '_token' => $csrfToken,
        ];

        return $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->post('/register', $payload);
    }

    /**
     * Returns the isolated legacy database connection.
     */
    private function gameDatabase(): Connection
    {
        $database = $this->app->make(DatabaseManager::class);

        return $database->connection('game');
    }

    /**
     * Inserts one complete existing legacy account fixture.
     */
    private function insertExistingAccount(string $username): void
    {
        $accountGuid = $this->gameDatabase()
            ->table('MEMB_INFO')
            ->insertGetId([
                'memb___id' => $username,
                'memb__pwd' => 'existing',
                'memb_name' => $username,
                'sno__numb' => '1111111111111',
                'mail_addr' => 'existing@example.com',
                'bloc_code' => '0',
                'ctl1_code' => '0',
                'IsVip' => 0,
                'VipExpirationTime' => 0,
            ], 'memb_guid');

        $this->gameDatabase()
            ->table('VI_CURR_INFO')
            ->insert([
                'chek_code' => '1',
                'memb___id' => $username,
                'memb_name' => $username,
                'memb_guid' => $accountGuid,
                'sno__numb' => '1111111111111',
            ]);
    }
}
