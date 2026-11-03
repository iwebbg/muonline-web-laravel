<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Mail\AccountDeletionCodeMail;
use App\Models\AccountDeletionRequest;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Verifies password and email-code ownership checks for account deletion requests.
 */
final class AccountDeletionRequestTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Creates one isolated legacy account used by Laravel's custom auth provider.
     */
    protected function setUp(): void
    {
        parent::setUp();

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
            'CREATE TABLE MEMB_INFO (memb_guid INTEGER PRIMARY KEY, memb___id TEXT NOT NULL, memb__pwd TEXT NOT NULL, memb_name TEXT NOT NULL, mail_addr TEXT NULL, bloc_code TEXT NOT NULL, IsVip INTEGER NOT NULL)',
        );
        $database->connection('game')->table('MEMB_INFO')->insert([
            'memb_guid' => 1,
            'memb___id' => 'owner',
            'memb__pwd' => 'secret1',
            'memb_name' => 'Owner',
            'mail_addr' => 'owner@example.com',
            'bloc_code' => '0',
            'IsVip' => 0,
        ]);

        Mail::fake();
        RateLimiter::clear(md5('account-deletionowner|127.0.0.1'));
    }

    /**
     * Confirms the current password is required before any email is sent.
     */
    public function test_wrong_password_cannot_create_deletion_request(): void
    {
        $response = $this
            ->actingAs($this->account())
            ->withSession(['_token' => 'deletion-test-token'])
            ->post(route('account.deletion-requests.store'), [
                'currentPassword' => 'wrong',
                '_token' => 'deletion-test-token',
            ]);

        $response->assertSessionHasErrors('currentPassword');
        $this->assertSame(0, AccountDeletionRequest::query()->count());
        Mail::assertNothingSent();
    }

    /**
     * Confirms a valid email code marks the owned request verified exactly once.
     */
    public function test_email_code_verifies_owned_deletion_request(): void
    {
        $this
            ->actingAs($this->account())
            ->withSession(['_token' => 'deletion-test-token'])
            ->post(route('account.deletion-requests.store'), [
                'currentPassword' => 'secret1',
                '_token' => 'deletion-test-token',
            ])
            ->assertSessionHas('deletionStatus', 'A verification code was sent to your account email.');

        $mail = Mail::sent(AccountDeletionCodeMail::class)->first();

        $this->assertInstanceOf(AccountDeletionCodeMail::class, $mail);

        $deletionRequest = AccountDeletionRequest::query()->sole();
        $response = $this
            ->actingAs($this->account())
            ->withSession(['_token' => 'deletion-test-token'])
            ->post(route('account.deletion-requests.confirm'), [
                'deletionRequest' => $deletionRequest->getKey(),
                'verificationCode' => $mail->verificationCode,
                '_token' => 'deletion-test-token',
            ]);

        $response
            ->assertRedirect(route('account.security.index'))
            ->assertSessionHas('deletionStatus', 'Your account deletion request was verified.');
        $this->assertSame('verified', $deletionRequest->fresh()?->status);
        $this->assertNotNull($deletionRequest->fresh()?->verified_at);

        $this
            ->actingAs($this->account())
            ->withSession(['_token' => 'deletion-test-token'])
            ->post(route('account.deletion-requests.confirm'), [
                'deletionRequest' => $deletionRequest->getKey(),
                'verificationCode' => $mail->verificationCode,
                '_token' => 'deletion-test-token',
            ])
            ->assertSessionHasErrors('verificationCode');
    }

    /**
     * Builds one authenticated account matching the isolated legacy record.
     */
    private function account(): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: 'owner',
            displayName: 'Owner',
            email: 'owner@example.com',
            blocked: false,
            vip: false,
            password: 'secret1',
        );
    }
}
