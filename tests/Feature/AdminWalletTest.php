<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Models\AccountWallet;
use App\Models\Administrator;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies wallet endpoint permissions, validation, preview UI and audit output.
 */
final class AdminWalletTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms only administrators with economy permission can open the wallet ledger.
     */
    public function test_wallet_manager_requires_economy_permission(): void
    {
        $economyManager = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $moderator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ModerateAccounts])
            ->create();

        $this->actingAs($economyManager, 'admin')
            ->get('/admin/economy/wallet?account=wallet01')
            ->assertOk()
            ->assertSee('walletManager(', false);
        $this->actingAs($moderator, 'admin')
            ->get('/admin/economy/wallet')
            ->assertForbidden();
    }

    /**
     * Confirms a valid administrator credit updates the wallet and shared audit log.
     */
    public function test_administrator_credits_wallet_and_audit_log_records_transition(): void
    {
        $administrator = Administrator::factory()->create();
        $lookup = $this->mock(GameAccountLookupRepository::class);
        $lookup->shouldReceive('exists')->once()->with('wallet05')->andReturnTrue();
        $csrfToken = 'admin-wallet-credit-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post('/admin/economy/wallet', [
                '_token' => $csrfToken,
                'account_id' => 'wallet05',
                'direction' => 'credit',
                'amount' => 500,
                'reason' => 'Tournament reward correction',
                'idempotency_key' => 'a5d6ab2b-3d50-436a-ad1b-d0bccda2774a',
            ]);

        $response
            ->assertRedirect('/admin/economy/wallet?account=wallet05')
            ->assertSessionHasNoErrors();
        $this->assertSame(500, AccountWallet::query()->sole()->balance);
        $this->assertSame(500, WalletTransaction::query()->sole()->amount);

        $this->actingAs($administrator, 'admin')
            ->get('/admin/audit-log?type=wallet_adjustment')
            ->assertOk()
            ->assertSee('Wallet adjustment')
            ->assertSee('wallet05')
            ->assertSee('Tournament reward correction')
            ->assertSee('500');
    }

    /**
     * Confirms an unknown game account produces no application-owned wallet rows.
     */
    public function test_unknown_game_account_is_rejected_without_wallet_write(): void
    {
        $administrator = Administrator::factory()->create();
        $lookup = $this->mock(GameAccountLookupRepository::class);
        $lookup->shouldReceive('exists')->once()->with('missing1')->andReturnFalse();
        $csrfToken = 'admin-wallet-missing-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->from('/admin/economy/wallet?account=missing1')
            ->post('/admin/economy/wallet', [
                '_token' => $csrfToken,
                'account_id' => 'missing1',
                'direction' => 'credit',
                'amount' => 100,
                'reason' => 'Should never be written',
                'idempotency_key' => '46f1fe7b-c504-4715-b737-8c07e62c8f96',
            ]);

        $response
            ->assertRedirect('/admin/economy/wallet?account=missing1')
            ->assertSessionHasErrors('adjustment');
        $this->assertSame(0, AccountWallet::query()->count());
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    /**
     * Confirms free-text ledger reasons remain escaped in the administrator view.
     */
    public function test_wallet_history_escapes_audit_reason(): void
    {
        $administrator = Administrator::factory()->create();
        $wallet = AccountWallet::factory()->create([
            'account_id' => 'wallet06',
            'balance' => 10,
        ]);
        WalletTransaction::factory()->create([
            'account_wallet_id' => $wallet->getKey(),
            'account_id' => 'wallet06',
            'balance_before' => 0,
            'balance_after' => 10,
            'amount' => 10,
            'reason' => '<script>alert("wallet-xss")</script>',
        ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/economy/wallet?account=wallet06');

        $response
            ->assertOk()
            ->assertDontSee('<script>alert("wallet-xss")</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }
}
