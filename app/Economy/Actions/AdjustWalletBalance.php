<?php

namespace App\Economy\Actions;

use App\Economy\Exceptions\WalletAdjustmentRejected;
use App\Economy\WalletAdjustmentBlockReason;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Models\AccountWallet;
use App\Models\WalletTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Applies one idempotent, append-only administrator wallet adjustment atomically.
 */
final readonly class AdjustWalletBalance
{
    private const MAX_BALANCE = 9_000_000_000_000_000;

    /**
     * Creates the action with the active game-account existence boundary.
     */
    public function __construct(
        private GameAccountLookupRepository $accountLookup,
    ) {}

    /**
     * Applies one signed balance delta and returns the immutable ledger entry.
     *
     * @throws WalletAdjustmentRejected When account, balance or idempotency checks fail.
     */
    public function execute(
        string $accountId,
        WalletCurrency $currency,
        WalletTransactionType $type,
        int $amount,
        string $reason,
        ?int $administratorId,
        string $idempotencyKey,
    ): WalletTransaction {
        $isCredit = in_array(
            $type,
            [
                WalletTransactionType::AdminCredit,
                WalletTransactionType::Reward,
                WalletTransactionType::Refund,
            ],
            true,
        ) && $amount > 0;
        $isDebit = in_array(
            $type,
            [
                WalletTransactionType::AdminDebit,
                WalletTransactionType::Purchase,
            ],
            true,
        ) && $amount < 0;

        if (! $isCredit && ! $isDebit) {
            throw new WalletAdjustmentRejected(
                WalletAdjustmentBlockReason::InvalidAdjustment,
            );
        }

        $requestHash = $this->requestHash(
            accountId: $accountId,
            currency: $currency,
            type: $type,
            amount: $amount,
            reason: $reason,
            administratorId: $administratorId,
        );
        $existingTransaction = WalletTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingTransaction instanceof WalletTransaction) {
            return $this->resolveExisting($existingTransaction, $requestHash);
        }

        if (! $this->accountLookup->exists($accountId)) {
            throw new WalletAdjustmentRejected(WalletAdjustmentBlockReason::AccountNotFound);
        }

        try {
            return DB::transaction(function () use (
                $accountId,
                $currency,
                $type,
                $amount,
                $reason,
                $administratorId,
                $idempotencyKey,
                $requestHash,
            ): WalletTransaction {
                AccountWallet::query()->insertOrIgnore([
                    'account_id' => $accountId,
                    'currency' => $currency->value,
                    'balance' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $wallet = AccountWallet::query()
                    ->where('account_id', $accountId)
                    ->where('currency', $currency)
                    ->lockForUpdate()
                    ->firstOrFail();
                $balanceBefore = $wallet->balance;
                $balanceAfter = $balanceBefore + $amount;

                if ($balanceAfter < 0) {
                    throw new WalletAdjustmentRejected(
                        WalletAdjustmentBlockReason::InsufficientBalance,
                    );
                }

                if ($balanceAfter > self::MAX_BALANCE) {
                    throw new WalletAdjustmentRejected(
                        WalletAdjustmentBlockReason::BalanceLimitExceeded,
                    );
                }

                $wallet->update([
                    'balance' => $balanceAfter,
                ]);

                return WalletTransaction::query()->create([
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'account_wallet_id' => $wallet->getKey(),
                    'account_id' => $accountId,
                    'currency' => $currency->value,
                    'type' => $type->value,
                    'amount' => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'reason' => $reason,
                    'metadata' => null,
                    'performed_by_administrator_id' => $administratorId,
                ]);
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existingTransaction = WalletTransaction::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingTransaction instanceof WalletTransaction) {
                return $this->resolveExisting($existingTransaction, $requestHash);
            }

            throw $exception;
        }
    }

    /**
     * Returns an identical replay or rejects reuse for different adjustment data.
     */
    private function resolveExisting(
        WalletTransaction $transaction,
        string $requestHash,
    ): WalletTransaction {
        if (! hash_equals($transaction->request_hash, $requestHash)) {
            throw new WalletAdjustmentRejected(
                WalletAdjustmentBlockReason::IdempotencyConflict,
            );
        }

        return $transaction;
    }

    /**
     * Produces the deterministic fingerprint bound to one idempotency key.
     */
    private function requestHash(
        string $accountId,
        WalletCurrency $currency,
        WalletTransactionType $type,
        int $amount,
        string $reason,
        ?int $administratorId,
    ): string {
        $payload = json_encode([
            'account_id' => $accountId,
            'currency' => $currency->value,
            'type' => $type->value,
            'amount' => $amount,
            'reason' => $reason,
            'administrator_id' => $administratorId,
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $payload);
    }
}
