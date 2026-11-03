<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Models\AccountWallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the account-scoped Credits balance and paginated ledger page.
 */
final class AccountCreditPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms guests cannot inspect an account Credits ledger.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('account.credits.index'));

        $response->assertRedirect(route('login'));
    }

    /**
     * Confirms the page exposes only the authenticated account's escaped ledger.
     */
    public function test_page_displays_owned_balance_and_transaction_history(): void
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
            'reason' => 'Other account private transaction',
        ]);

        $response = $this
            ->actingAs($this->account('owner'))
            ->get(route('account.credits.index'));

        $response
            ->assertOk()
            ->assertSee('Credits wallet')
            ->assertSee('125')
            ->assertDontSee('<script>alert("private-wallet")</script>', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('Other account private transaction');
    }

    /**
     * Builds one authenticated legacy account identity.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: $username,
            displayName: 'Credits Player',
            email: 'player@example.com',
            blocked: false,
            vip: false,
            password: 'secret1',
        );
    }
}
