<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Models\AccountWallet;
use App\Models\WalletTransaction;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that private character data remains scoped to the authenticated account.
 */
final class AccountDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Creates isolated 97d+99i character and online-status tables.
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

        $connection = $database->connection('game');

        $connection->statement('CREATE TABLE MEMB_STAT (memb___id TEXT PRIMARY KEY, ConnectStat INTEGER NULL)');
        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, cLevel INTEGER NULL, LevelUpPoint INTEGER NULL, Class INTEGER NULL, Strength INTEGER NULL, Dexterity INTEGER NULL, Vitality INTEGER NULL, Energy INTEGER NULL, Money INTEGER NULL, MapNumber INTEGER NULL, Resets INTEGER NOT NULL, GrandResets INTEGER NOT NULL)');
    }

    /**
     * Confirms that the dashboard renders an owned character and hides another account.
     */
    public function test_dashboard_displays_only_characters_owned_by_authenticated_account(): void
    {
        $database = $this->app->make(DatabaseManager::class);
        $connection = $database->connection('game');

        $connection->table('MEMB_STAT')->insert([
            ['memb___id' => 'owner', 'ConnectStat' => 0],
            ['memb___id' => 'other', 'ConnectStat' => 1],
        ]);
        $connection->table('Character')->insert([
            $this->characterRecord('owner', 'OwnedKnight'),
            $this->characterRecord('other', 'HiddenWizard', 1),
        ]);

        $response = $this
            ->actingAs($this->account('owner'))
            ->get('/account');

        $response->assertOk();
        $response->assertSee('OwnedKnight');
        $response->assertDontSee('HiddenWizard');
        $response->assertSee('Blade Knight');
        $response->assertSee('Lorencia');
        $response->assertSee('Grand reset');
        $response->assertSee('grand-reset-preview', false);
        $response->assertSee('character-grand-reset', false);
        $response->assertSee('stat-reset-preview', false);
        $response->assertSee('character-stat-reset', false);
        $response->assertSee('pk-clear-preview', false);
        $response->assertSee('character-pk-clear', false);
        $response->assertSee('move-to-town-preview', false);
        $response->assertSee('character-move', false);
        $response->assertSee('item-storage', false);
        $response->assertSee('x-if="itemStorage !== null', false);
        $response->assertSee('Account security');
        $response->assertSee('account/credentials', false);
        $response->assertSee('x-teleport="body"', false);
        $response->assertSee('aria-modal="true"', false);
        $response->assertSee('credentialsOpen: false', false);
    }

    /**
     * Confirms that an account with no characters receives a useful empty state.
     */
    public function test_dashboard_displays_empty_state_when_account_has_no_characters(): void
    {
        $response = $this
            ->actingAs($this->account('empty'))
            ->get('/account');

        $response->assertOk();
        $response->assertSee('No characters found');
        $response->assertSee('Account security');
    }

    /**
     * Confirms credential validation feedback reopens the modal where the form is displayed.
     */
    public function test_dashboard_reopens_account_security_modal_after_validation_error(): void
    {
        $response = $this
            ->withSession([
                '_old_input' => [
                    'email' => 'invalid-address',
                ],
            ])
            ->actingAs($this->account('owner'))
            ->get('/account');

        $response->assertOk();
        $response->assertSee('value="invalid-address"', false);
        $response->assertSee('credentialsOpen: true', false);
    }

    /**
     * Confirms players see only their Credits balance and safely escaped ledger history.
     */
    public function test_dashboard_displays_owned_wallet_and_transaction_history(): void
    {
        $wallet = AccountWallet::factory()->create([
            'account_id' => 'owner',
            'balance' => 125,
        ]);
        WalletTransaction::factory()->create([
            'account_wallet_id' => $wallet->getKey(),
            'account_id' => 'owner',
            'amount' => 125,
            'balance_before' => 0,
            'balance_after' => 125,
            'reason' => '<script>alert("private-wallet")</script>',
        ]);
        $otherWallet = AccountWallet::factory()->create([
            'account_id' => 'other',
            'balance' => 999,
        ]);
        WalletTransaction::factory()->create([
            'account_wallet_id' => $otherWallet->getKey(),
            'account_id' => 'other',
            'amount' => 999,
            'balance_before' => 0,
            'balance_after' => 999,
            'reason' => 'Other account private transaction',
        ]);

        $response = $this
            ->actingAs($this->account('owner'))
            ->get('/account');

        $response
            ->assertOk()
            ->assertSee('125')
            ->assertSee('Credits and transaction history')
            ->assertDontSee('<script>alert("private-wallet")</script>', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('Other account private transaction');
    }

    /**
     * Creates one authenticated test account identity.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: $username,
            displayName: 'Test User',
            email: null,
            blocked: false,
            vip: false,
            password: 'secret1',
        );
    }

    /**
     * Creates one portable Character table record for an ownership test.
     *
     * @return array<string, int|string>
     */
    private function characterRecord(string $accountId, string $name, int $classCode = 17): array
    {
        return [
            'AccountID' => $accountId,
            'Name' => $name,
            'cLevel' => 400,
            'LevelUpPoint' => 1200,
            'Class' => $classCode,
            'Strength' => 1000,
            'Dexterity' => 800,
            'Vitality' => 700,
            'Energy' => 200,
            'Money' => 200_000_000,
            'MapNumber' => 0,
            'Resets' => 20,
            'GrandResets' => 2,
        ];
    }
}
