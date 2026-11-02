<?php

namespace Tests\Feature;

use App\AccountCredentials\AccountCredentialStatus;
use App\Mail\GameAccountPasswordResetMail;
use App\Models\AccountPasswordRecoveryOperation;
use App\Models\GameAccountPasswordReset;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Verifies enumeration-safe email delivery and single-use legacy password resets.
 */
final class AccountPasswordRecoveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Creates an isolated legacy account table with recovery writes enabled.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.writes_enabled', true);
        config()->set('game.account_password_scheme', 'plaintext');
        config()->set('game.password_reset_ttl_minutes', 60);
        config()->set('database.connections.game', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
            'readonly' => false,
        ]);
        $this->withoutMiddleware(ThrottleRequests::class);

        $database = $this->app->make(DatabaseManager::class);

        $database->purge('game');

        $this->gameConnection()->statement('CREATE TABLE MEMB_INFO (memb___id TEXT PRIMARY KEY, memb__pwd TEXT NOT NULL, mail_addr TEXT NULL)');
        $this->gameConnection()->table('MEMB_INFO')->insert([
            'memb___id' => 'testacct',
            'memb__pwd' => 'OldPass1',
            'mail_addr' => 'Player@Example.com',
        ]);

        Mail::fake();
    }

    /**
     * Confirms an exact account/email pair receives one hashed, expiring token.
     */
    public function test_matching_account_receives_password_reset_mail(): void
    {
        $response = $this->postLinkRequest('testacct', 'player@example.com');

        $response
            ->assertRedirect()
            ->assertSessionHas('status');
        Mail::assertSent(
            GameAccountPasswordResetMail::class,
            static fn (GameAccountPasswordResetMail $mail): bool => $mail->hasTo('Player@Example.com'),
        );

        $token = GameAccountPasswordReset::query()->sole();
        $operation = AccountPasswordRecoveryOperation::query()->sole();

        $this->assertSame(64, strlen($token->token_hash));
        $this->assertNotSame('testacct', $token->token_hash);
        $this->assertTrue($token->expires_at->isFuture());
        $this->assertNull($token->used_at);
        $this->assertSame(AccountCredentialStatus::Completed, $operation->status);
        $this->assertTrue($operation->after_snapshot['recipient_matched']);
    }

    /**
     * Confirms mismatches return the same public response without sending mail.
     */
    public function test_unknown_recipient_does_not_leak_account_existence(): void
    {
        $matchedResponse = $this->postLinkRequest('testacct', 'wrong@example.com');
        $unknownResponse = $this->postLinkRequest('missing1', 'wrong@example.com');

        $matchedResponse->assertRedirect()->assertSessionHas('status');
        $unknownResponse->assertRedirect()->assertSessionHas('status');
        $this->assertSame(
            $matchedResponse->getSession()->get('status'),
            $unknownResponse->getSession()->get('status'),
        );
        Mail::assertNothingSent();
        $this->assertSame(0, GameAccountPasswordReset::query()->count());
        $this->assertSame(2, AccountPasswordRecoveryOperation::query()->count());
    }

    /**
     * Confirms one valid token changes the password once and records no secrets.
     */
    public function test_valid_token_resets_password_once_without_auditing_secrets(): void
    {
        $rawToken = str_repeat('A', 64);
        $this->insertResetToken($rawToken);

        $response = $this->postPasswordReset($rawToken, 'NewPass2');

        $response
            ->assertRedirect('/login')
            ->assertSessionHas('status');

        $operation = AccountPasswordRecoveryOperation::query()->sole();

        $this->assertSame('NewPass2', $this->storedPassword());
        $this->assertNotNull(GameAccountPasswordReset::query()->sole()->used_at);
        $this->assertSame(AccountCredentialStatus::Completed, $operation->status);
        $this->assertStringNotContainsString('NewPass2', (string) json_encode($operation->toArray()));

        $reuseResponse = $this->postPasswordReset($rawToken, 'Again333');

        $reuseResponse->assertSessionHasErrors('token');
        $this->assertSame('NewPass2', $this->storedPassword());
        $this->assertSame(AccountCredentialStatus::Failed, AccountPasswordRecoveryOperation::query()->latest('id')->firstOrFail()->status);
    }

    /**
     * Confirms expired tokens cannot mutate the legacy password.
     */
    public function test_expired_token_is_rejected_without_password_write(): void
    {
        $rawToken = str_repeat('B', 64);
        $this->insertResetToken($rawToken, expired: true);

        $response = $this->postPasswordReset($rawToken, 'NewPass2');

        $response->assertSessionHasErrors('token');
        $this->assertSame('OldPass1', $this->storedPassword());
        $this->assertNull(GameAccountPasswordReset::query()->sole()->used_at);
    }

    /**
     * Confirms disabled writes reject before consuming a valid token.
     */
    public function test_disabled_writes_preserve_token_and_password(): void
    {
        $rawToken = str_repeat('C', 64);
        $this->insertResetToken($rawToken);
        config()->set('game.writes_enabled', false);

        $response = $this->postPasswordReset($rawToken, 'NewPass2');

        $response->assertSessionHasErrors('token');
        $this->assertSame('OldPass1', $this->storedPassword());
        $this->assertNull(GameAccountPasswordReset::query()->sole()->used_at);
        $this->assertSame(0, AccountPasswordRecoveryOperation::query()->count());
    }

    /**
     * Confirms the request and reset screens are publicly reachable.
     */
    public function test_password_recovery_screens_render(): void
    {
        $token = str_repeat('D', 64);

        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Secure account recovery');
        $this->get('/reset-password/testacct/'.$token)
            ->assertOk()
            ->assertSee('testacct');
    }

    /**
     * Inserts one hashed token directly into the application-owned token store.
     */
    private function insertResetToken(string $rawToken, bool $expired = false): void
    {
        GameAccountPasswordReset::query()->create([
            'account_id' => 'testacct',
            'token_hash' => hash('sha256', $rawToken),
            'email_hash' => hash('sha256', 'player@example.com'),
            'expires_at' => $expired ? now()->subMinute() : now()->addHour(),
        ]);
    }

    /**
     * Sends one CSRF-protected reset-link request.
     */
    private function postLinkRequest(string $accountId, string $email): TestResponse
    {
        $csrfToken = 'password-recovery-link-token';

        return $this->withSession(['_token' => $csrfToken])->post('/forgot-password', [
            '_token' => $csrfToken,
            'account' => $accountId,
            'email' => $email,
        ]);
    }

    /**
     * Sends one CSRF-protected password replacement request.
     */
    private function postPasswordReset(string $rawToken, string $password): TestResponse
    {
        $csrfToken = 'password-recovery-reset-token';

        return $this->withSession(['_token' => $csrfToken])->post('/reset-password', [
            '_token' => $csrfToken,
            'account' => 'testacct',
            'token' => $rawToken,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    /**
     * Returns the isolated game database connection.
     */
    private function gameConnection(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }

    /**
     * Reads the current legacy password for exact mutation assertions.
     */
    private function storedPassword(): string
    {
        return (string) $this->gameConnection()
            ->table('MEMB_INFO')
            ->where('memb___id', 'testacct')
            ->value('memb__pwd');
    }
}
