<?php

namespace Tests\Feature;

use App\AccountCredentials\AccountCredentialStatus;
use App\Auth\GameAccount;
use App\Models\AccountCredentialOperation;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Verifies guarded and audited updates to authenticated legacy account credentials.
 */
final class AccountCredentialUpdateEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Creates an isolated MEMB_INFO table and enables test-only game writes.
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
        $database->connection('game')->statement(
            'CREATE TABLE MEMB_INFO (memb_guid INTEGER PRIMARY KEY AUTOINCREMENT, memb___id TEXT NOT NULL UNIQUE, memb__pwd TEXT NOT NULL, memb_name TEXT NOT NULL, mail_addr TEXT NULL, bloc_code TEXT NOT NULL, IsVip INTEGER NOT NULL)',
        );

        RateLimiter::clear(md5('account-credentialsowner|127.0.0.1'));
        RateLimiter::clear(md5('account-credentialsowner|203.0.113.20'));
    }

    /**
     * Confirms unauthenticated callers cannot reach the credential mutation.
     */
    public function test_guest_is_redirected_to_login_without_mutation(): void
    {
        $this->insertAccount('owner');

        $response = $this->putWithCsrf('/account/credentials');

        $response->assertRedirect('/login');
        $this->assertSame('secret1', $this->accountRecord('owner')->memb__pwd);
        $this->assertSame(0, AccountCredentialOperation::query()->count());
    }

    /**
     * Confirms the owned account email and password change atomically and are audited safely.
     */
    public function test_authenticated_account_can_change_email_and_password(): void
    {
        $this->insertAccount('owner');
        $this->insertAccount('other', 'otherpass', 'other@example.com');
        $this->actingAs($this->account('owner'));

        $response = $this->putWithCsrf('/account/credentials', [
            'currentPassword' => 'secret1',
            'email' => 'NEW@EXAMPLE.COM',
            'password' => 'new_pass2',
            'password_confirmation' => 'new_pass2',
            'accountId' => 'other',
            'bloc_code' => '1',
        ]);

        $response
            ->assertRedirect('/account/security')
            ->assertSessionHas('accountStatus', 'Your account email and password settings were updated successfully.');

        $owner = $this->accountRecord('owner');
        $other = $this->accountRecord('other');

        $this->assertSame('new_pass2', $owner->memb__pwd);
        $this->assertSame('new@example.com', $owner->mail_addr);
        $this->assertSame('0', $owner->bloc_code);
        $this->assertSame('otherpass', $other->memb__pwd);
        $this->assertSame('other@example.com', $other->mail_addr);

        $operation = AccountCredentialOperation::query()->sole();
        $serializedAudit = json_encode($operation->toArray(), JSON_THROW_ON_ERROR);

        $this->assertSame(AccountCredentialStatus::Completed, $operation->status);
        $this->assertTrue($operation->after_snapshot['email_changed']);
        $this->assertTrue($operation->after_snapshot['password_changed']);
        $this->assertStringNotContainsString('secret1', $serializedAudit);
        $this->assertStringNotContainsString('new_pass2', $serializedAudit);
        $this->assertStringNotContainsString('new@example.com', $serializedAudit);
    }

    /**
     * Confirms the email may change without replacing the game password.
     */
    public function test_authenticated_account_can_change_only_email(): void
    {
        $this->insertAccount('owner');
        $this->actingAs($this->account('owner'));

        $response = $this->putWithCsrf('/account/credentials', [
            'currentPassword' => 'secret1',
            'email' => 'updated@example.com',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertRedirect('/account/security');

        $owner = $this->accountRecord('owner');
        $operation = AccountCredentialOperation::query()->sole();

        $this->assertSame('secret1', $owner->memb__pwd);
        $this->assertSame('updated@example.com', $owner->mail_addr);
        $this->assertTrue($operation->after_snapshot['email_changed']);
        $this->assertFalse($operation->after_snapshot['password_changed']);
    }

    /**
     * Confirms an incorrect current password leaves the game row unchanged.
     */
    public function test_incorrect_current_password_is_rejected_and_audited(): void
    {
        $this->insertAccount('owner');
        $this->actingAs($this->account('owner'));
        $this->from('/account/security');

        $response = $this->putWithCsrf('/account/credentials', [
            'currentPassword' => 'wrong',
            'email' => 'updated@example.com',
        ]);

        $response
            ->assertRedirect('/account/security')
            ->assertSessionHasErrors([
                'currentPassword' => 'The current password is incorrect.',
            ]);

        $owner = $this->accountRecord('owner');
        $operation = AccountCredentialOperation::query()->sole();

        $this->assertSame('secret1', $owner->memb__pwd);
        $this->assertSame('owner@example.com', $owner->mail_addr);
        $this->assertSame(AccountCredentialStatus::Failed, $operation->status);
        $this->assertSame('current_password_invalid', $operation->failure_code);
    }

    /**
     * Confirms submitting the existing state causes no game write.
     */
    public function test_unchanged_credentials_are_rejected_without_game_write(): void
    {
        $this->insertAccount('owner');
        $this->actingAs($this->account('owner'));
        $this->from('/account/security');

        $response = $this->putWithCsrf('/account/credentials', [
            'currentPassword' => 'secret1',
            'email' => 'OWNER@EXAMPLE.COM',
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
        ]);

        $response
            ->assertRedirect('/account/security')
            ->assertSessionHasErrors([
                'email' => 'Enter a different email address or a different new password.',
            ]);

        $operation = AccountCredentialOperation::query()->sole();

        $this->assertSame(AccountCredentialStatus::Failed, $operation->status);
        $this->assertSame('no_changes', $operation->failure_code);
    }

    /**
     * Confirms malformed replacement data is rejected without audit or game writes.
     */
    public function test_invalid_fields_are_rejected_without_exposing_password_input(): void
    {
        $this->insertAccount('owner');
        $this->actingAs($this->account('owner'));
        $this->from('/account/security');

        $response = $this->putWithCsrf('/account/credentials', [
            'currentPassword' => 'secret1',
            'email' => 'invalid-email',
            'password' => 'bad password',
            'password_confirmation' => 'different',
        ]);

        $response
            ->assertRedirect('/account/security')
            ->assertSessionHasErrors([
                'email' => 'Enter a valid email address.',
                'password' => 'The new password must contain between 4 and 10 characters.',
            ])
            ->assertSessionMissing('_old_input.currentPassword')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');

        $owner = $this->accountRecord('owner');

        $this->assertSame('secret1', $owner->memb__pwd);
        $this->assertSame('owner@example.com', $owner->mail_addr);
        $this->assertSame(0, AccountCredentialOperation::query()->count());
    }

    /**
     * Confirms the global database safety switch blocks credential mutations.
     */
    public function test_read_only_connection_rejects_update_without_audit(): void
    {
        $this->insertAccount('owner');
        $this->actingAs($this->account('owner'));
        config()->set('database.connections.game.readonly', true);

        $response = $this->putWithCsrf('/account/credentials');

        $response->assertServiceUnavailable();
        $this->assertSame('secret1', $this->accountRecord('owner')->memb__pwd);
        $this->assertSame(0, AccountCredentialOperation::query()->count());
    }

    /**
     * Confirms the sixth credential attempt for one account and IP is blocked.
     */
    public function test_credential_updates_are_rate_limited_after_five_attempts(): void
    {
        $this->insertAccount('owner');
        $this->actingAs($this->account('owner'));
        $serverVariables = [
            'REMOTE_ADDR' => '203.0.113.20',
        ];

        $this->withServerVariables($serverVariables);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $response = $this->putWithCsrf('/account/credentials', [
                'currentPassword' => 'wrong',
                'email' => 'owner@example.com',
            ]);

            $response->assertSessionHasErrors('currentPassword');
        }

        $blockedResponse = $this->putWithCsrf('/account/credentials', [
            'currentPassword' => 'wrong',
            'email' => 'owner@example.com',
        ]);

        $blockedResponse->assertTooManyRequests();
    }

    /**
     * Sends one credential update through the browser CSRF boundary.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function putWithCsrf(string $uri, array $overrides = []): TestResponse
    {
        $csrfToken = 'credential-update-test-token';
        $payload = [
            'currentPassword' => 'secret1',
            'email' => 'updated@example.com',
            'password' => null,
            'password_confirmation' => null,
            ...$overrides,
            '_token' => $csrfToken,
        ];

        return $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->put($uri, $payload);
    }

    /**
     * Creates one authenticated identity matching the isolated game row.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: $username,
            displayName: 'Test User',
            email: $username.'@example.com',
            blocked: false,
            vip: false,
            password: 'secret1',
        );
    }

    /**
     * Inserts one complete legacy account fixture.
     */
    private function insertAccount(
        string $username,
        string $password = 'secret1',
        ?string $email = null,
    ): void {
        $this->gameDatabase()
            ->table('MEMB_INFO')
            ->insert([
                'memb___id' => $username,
                'memb__pwd' => $password,
                'memb_name' => $username,
                'mail_addr' => $email ?? $username.'@example.com',
                'bloc_code' => '0',
                'IsVip' => 0,
            ]);
    }

    /**
     * Returns one account fixture as an object for strict assertions.
     */
    private function accountRecord(string $username): object
    {
        return $this->gameDatabase()
            ->table('MEMB_INFO')
            ->where('memb___id', $username)
            ->firstOrFail();
    }

    /**
     * Returns the isolated legacy database connection.
     */
    private function gameDatabase(): Connection
    {
        $database = $this->app->make(DatabaseManager::class);

        return $database->connection('game');
    }
}
