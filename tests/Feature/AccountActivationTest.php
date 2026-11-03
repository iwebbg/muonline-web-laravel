<?php

namespace Tests\Feature;

use App\Mail\GameAccountActivationMail;
use App\Models\GameAccountActivation;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Verifies single-use email activation for blocked legacy game accounts.
 */
final class AccountActivationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Creates the minimal isolated MEMB_INFO activation boundary.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
            'readonly' => false,
        ]);

        $database = $this->app->make(DatabaseManager::class);

        $database->purge('game');
        $this->gameDatabase()->statement(
            'CREATE TABLE MEMB_INFO (memb___id TEXT PRIMARY KEY, memb__pwd TEXT NOT NULL, mail_addr TEXT NULL, bloc_code TEXT NOT NULL)',
        );
        $this->gameDatabase()->table('MEMB_INFO')->insert([
            'memb___id' => 'activate1',
            'memb__pwd' => 'secret1',
            'mail_addr' => 'player@example.com',
            'bloc_code' => '1',
        ]);

        Mail::fake();
        RateLimiter::clear(md5('account-activation-linkactivate1|127.0.0.1'));
        RateLimiter::clear(md5('account-activation-linkmissing1|127.0.0.1'));
    }

    /**
     * Confirms a valid link activates the game account and cannot be replayed.
     */
    public function test_valid_activation_link_unblocks_account_once(): void
    {
        $token = str_repeat('a', 64);
        $activation = GameAccountActivation::query()->create([
            'account_id' => 'activate1',
            'token_hash' => hash('sha256', $token),
            'email_hash' => hash('sha256', 'player@example.com'),
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->get(route('account.activation', [
            'account' => 'activate1',
            'token' => $token,
        ]));

        $response
            ->assertRedirect('/login')
            ->assertSessionHas('status', 'Your game account is activated. You can sign in now.');
        $this->assertSame('0', $this->gameDatabase()->table('MEMB_INFO')->value('bloc_code'));
        $this->assertNotNull($activation->fresh()?->used_at);

        $this->get(route('account.activation', [
            'account' => 'activate1',
            'token' => $token,
        ]))->assertNotFound();
    }

    /**
     * Confirms expired links never modify the legacy block flag.
     */
    public function test_expired_activation_link_is_rejected(): void
    {
        $token = str_repeat('b', 64);
        GameAccountActivation::query()->create([
            'account_id' => 'activate1',
            'token_hash' => hash('sha256', $token),
            'email_hash' => hash('sha256', 'player@example.com'),
            'expires_at' => now()->subMinute(),
        ]);

        $this->get(route('account.activation', [
            'account' => 'activate1',
            'token' => $token,
        ]))->assertNotFound();

        $this->assertSame('1', $this->gameDatabase()->table('MEMB_INFO')->value('bloc_code'));
    }

    /**
     * Confirms resend responses do not reveal whether an account and email match.
     */
    public function test_activation_link_can_be_resent_without_account_enumeration(): void
    {
        $matchingResponse = $this
            ->withSession(['_token' => 'activation-test-token'])
            ->post('/activate-account', [
                'account' => 'activate1',
                'email' => 'player@example.com',
                '_token' => 'activation-test-token',
            ]);
        $missingResponse = $this
            ->withSession(['_token' => 'activation-test-token'])
            ->post('/activate-account', [
                'account' => 'missing1',
                'email' => 'missing@example.com',
                '_token' => 'activation-test-token',
            ]);

        $matchingResponse->assertSessionHas('status', 'If the account and email match, a new activation link has been sent.');
        $missingResponse->assertSessionHas('status', 'If the account and email match, a new activation link has been sent.');
        Mail::assertSent(GameAccountActivationMail::class, 1);
    }

    /**
     * Returns the isolated legacy database connection.
     */
    private function gameDatabase(): Connection
    {
        return $this->app
            ->make(DatabaseManager::class)
            ->connection('game');
    }
}
