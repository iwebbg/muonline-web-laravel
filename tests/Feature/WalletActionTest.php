<?php

namespace Tests\Feature;

use App\Economy\Actions\AdjustWalletBalance;
use App\Economy\Exceptions\WalletAdjustmentRejected;
use App\Economy\WalletAdjustmentBlockReason;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Models\AccountWallet;
use App\Models\Administrator;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies atomic wallet balances, rollback behavior and idempotency contracts.
 */
final class WalletActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms a credit creates both the balance and its append-only ledger entry.
     */
    public function test_credit_creates_wallet_and_immutable_transaction(): void
    {
        $administrator = Administrator::factory()->create();
        $lookup = $this->mock(GameAccountLookupRepository::class);
        $lookup->shouldReceive('exists')->once()->with('wallet01')->andReturnTrue();

        $transaction = $this->action()->execute(
            accountId: 'wallet01',
            currency: WalletCurrency::Credits,
            type: WalletTransactionType::AdminCredit,
            amount: 250,
            reason: 'Initial test credits',
            administratorId: (int) $administrator->getKey(),
            idempotencyKey: '9c730e96-0d49-4ff7-979d-8b35ac35630b',
        );

        $this->assertSame(0, $transaction->balance_before);
        $this->assertSame(250, $transaction->balance_after);
        $this->assertSame(250, AccountWallet::query()->sole()->balance);
        $this->assertSame(1, WalletTransaction::query()->count());
    }

    /**
     * Confirms an identical retry returns the first result without a second write.
     */
    public function test_identical_idempotency_replay_does_not_change_balance_twice(): void
    {
        $administrator = Administrator::factory()->create();
        $lookup = $this->mock(GameAccountLookupRepository::class);
        $lookup->shouldReceive('exists')->once()->andReturnTrue();
        $arguments = [
            'accountId' => 'wallet02',
            'currency' => WalletCurrency::Credits,
            'type' => WalletTransactionType::AdminCredit,
            'amount' => 100,
            'reason' => 'Replay-safe credit',
            'administratorId' => (int) $administrator->getKey(),
            'idempotencyKey' => 'f22c6588-6082-47c4-a44d-d0e158703d44',
        ];

        $firstTransaction = $this->action()->execute(...$arguments);
        $replayedTransaction = $this->action()->execute(...$arguments);

        $this->assertSame($firstTransaction->getKey(), $replayedTransaction->getKey());
        $this->assertSame(100, AccountWallet::query()->sole()->balance);
        $this->assertSame(1, WalletTransaction::query()->count());
    }

    /**
     * Confirms reusing an idempotency key for different data is rejected.
     */
    public function test_idempotency_key_reuse_for_different_data_is_rejected(): void
    {
        $administrator = Administrator::factory()->create();
        $lookup = $this->mock(GameAccountLookupRepository::class);
        $lookup->shouldReceive('exists')->once()->andReturnTrue();
        $action = $this->action();
        $idempotencyKey = '382aa298-1351-4396-a199-62833fed6387';

        $action->execute(
            accountId: 'wallet03',
            currency: WalletCurrency::Credits,
            type: WalletTransactionType::AdminCredit,
            amount: 100,
            reason: 'Original credit',
            administratorId: (int) $administrator->getKey(),
            idempotencyKey: $idempotencyKey,
        );

        try {
            $action->execute(
                accountId: 'wallet03',
                currency: WalletCurrency::Credits,
                type: WalletTransactionType::AdminCredit,
                amount: 200,
                reason: 'Different credit',
                administratorId: (int) $administrator->getKey(),
                idempotencyKey: $idempotencyKey,
            );
            $this->fail('A conflicting idempotency replay must be rejected.');
        } catch (WalletAdjustmentRejected $exception) {
            $this->assertSame(WalletAdjustmentBlockReason::IdempotencyConflict, $exception->reason);
        }

        $this->assertSame(100, AccountWallet::query()->sole()->balance);
        $this->assertSame(1, WalletTransaction::query()->count());
    }

    /**
     * Confirms an administrator cannot replay another administrator's request key.
     */
    public function test_idempotency_key_is_bound_to_the_original_administrator(): void
    {
        $originalAdministrator = Administrator::factory()->create();
        $secondAdministrator = Administrator::factory()->create();
        $lookup = $this->mock(GameAccountLookupRepository::class);
        $lookup->shouldReceive('exists')->once()->andReturnTrue();
        $action = $this->action();
        $idempotencyKey = '784f04e2-3fa3-493b-aa55-a508d0ec689a';

        $action->execute(
            accountId: 'wallet05',
            currency: WalletCurrency::Credits,
            type: WalletTransactionType::AdminCredit,
            amount: 100,
            reason: 'Administrator-bound credit',
            administratorId: (int) $originalAdministrator->getKey(),
            idempotencyKey: $idempotencyKey,
        );

        try {
            $action->execute(
                accountId: 'wallet05',
                currency: WalletCurrency::Credits,
                type: WalletTransactionType::AdminCredit,
                amount: 100,
                reason: 'Administrator-bound credit',
                administratorId: (int) $secondAdministrator->getKey(),
                idempotencyKey: $idempotencyKey,
            );
            $this->fail('A request key replayed by another administrator must be rejected.');
        } catch (WalletAdjustmentRejected $exception) {
            $this->assertSame(WalletAdjustmentBlockReason::IdempotencyConflict, $exception->reason);
        }

        $this->assertSame(100, AccountWallet::query()->sole()->balance);
        $this->assertSame(1, WalletTransaction::query()->count());
    }

    /**
     * Confirms an insufficient debit rolls back without appending a transaction.
     */
    public function test_insufficient_debit_preserves_balance_and_writes_no_transaction(): void
    {
        $administrator = Administrator::factory()->create();
        AccountWallet::factory()->create([
            'account_id' => 'wallet04',
            'balance' => 50,
        ]);
        $lookup = $this->mock(GameAccountLookupRepository::class);
        $lookup->shouldReceive('exists')->once()->andReturnTrue();

        try {
            $this->action()->execute(
                accountId: 'wallet04',
                currency: WalletCurrency::Credits,
                type: WalletTransactionType::AdminDebit,
                amount: -60,
                reason: 'Oversized debit',
                administratorId: (int) $administrator->getKey(),
                idempotencyKey: '88d9a248-389d-48fd-b9b4-c09dab46fe7e',
            );
            $this->fail('A debit below zero must be rejected.');
        } catch (WalletAdjustmentRejected $exception) {
            $this->assertSame(WalletAdjustmentBlockReason::InsufficientBalance, $exception->reason);
        }

        $this->assertSame(50, AccountWallet::query()->sole()->balance);
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    /**
     * Resolves the action through Laravel so the mocked lookup boundary is injected.
     */
    private function action(): AdjustWalletBalance
    {
        return $this->app->make(AdjustWalletBalance::class);
    }
}
