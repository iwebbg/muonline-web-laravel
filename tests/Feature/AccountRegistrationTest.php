<?php

namespace Tests\Feature;

use App\AccountRegistration\AccountRegistrationStatus;
use App\Models\AccountRegistrationOperation;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
        $response = $this->postRegistration([
            'username' => 'newhero',
            'email' => 'PLAYER@EXAMPLE.COM',
            'password' => 'secret_1',
            'password_confirmation' => 'secret_1',
            'bloc_code' => '1',
        ]);

        $response
            ->assertRedirect('/login')
            ->assertSessionHas('status', 'Your game account is ready. You can sign in now.');

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
        $this->assertSame('0', $account->bloc_code);
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
