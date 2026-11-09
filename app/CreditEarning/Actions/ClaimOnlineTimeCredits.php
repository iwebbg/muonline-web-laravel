<?php

namespace App\CreditEarning\Actions;

use App\CreditEarning\CreditEarningBlockReason;
use App\CreditEarning\Exceptions\CreditEarningRejected;
use App\Economy\Actions\AdjustWalletBalance;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Models\OnlineTimeAccount;
use App\Models\OnlineTimeClaim;
use App\Shop\EconomySettingReader;
use Illuminate\Database\DatabaseManager;
use Ramsey\Uuid\Uuid;

/**
 * Claims all currently earned online-time Credits in one application transaction.
 */
final readonly class ClaimOnlineTimeCredits
{
    /**
     * Creates the claim action with wallet and database transaction boundaries.
     */
    public function __construct(
        private AdjustWalletBalance $adjustWalletBalance,
        private DatabaseManager $database,
        private EconomySettingReader $economySettingReader,
    ) {}

    /**
     * Transfers claimable Credits exactly once and clears the pending balance.
     */
    public function execute(string $accountId, string $idempotencyKey): OnlineTimeClaim
    {
        $requestHash = hash('sha256', json_encode([
            'account_id' => $accountId,
        ], JSON_THROW_ON_ERROR));
        $existing = OnlineTimeClaim::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing instanceof OnlineTimeClaim) {
            return $this->resolveExisting($existing, $requestHash);
        }

        if (! $this->economySettingReader->current()->onlineTimeRewardsEnabled) {
            throw new CreditEarningRejected(CreditEarningBlockReason::ServiceDisabled);
        }

        return $this->database->transaction(function () use (
            $accountId,
            $idempotencyKey,
            $requestHash,
        ): OnlineTimeClaim {
            $account = OnlineTimeAccount::query()
                ->where('account_id', $accountId)
                ->lockForUpdate()
                ->first();

            $existing = OnlineTimeClaim::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing instanceof OnlineTimeClaim) {
                return $this->resolveExisting($existing, $requestHash);
            }

            if (! $account instanceof OnlineTimeAccount || $account->claimable_credits < 1) {
                throw new CreditEarningRejected(CreditEarningBlockReason::NothingToClaim);
            }

            $credits = $account->claimable_credits;
            $walletTransaction = $this->adjustWalletBalance->execute(
                accountId: $accountId,
                currency: WalletCurrency::Credits,
                type: WalletTransactionType::Reward,
                amount: $credits,
                reason: 'Online time reward',
                administratorId: null,
                idempotencyKey: Uuid::uuid5(
                    Uuid::NAMESPACE_URL,
                    'muonline-online-time-claim:'.$idempotencyKey,
                )->toString(),
            );
            $claim = OnlineTimeClaim::query()->create([
                'public_id' => (string) Uuid::uuid4(),
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'account_id' => $accountId,
                'credits_claimed' => $credits,
                'wallet_transaction_id' => $walletTransaction->getKey(),
                'completed_at' => now(),
            ]);
            $account->update(['claimable_credits' => 0]);

            return $claim;
        }, attempts: 3);
    }

    /**
     * Resolves an identical replay or rejects key reuse for another account.
     */
    private function resolveExisting(OnlineTimeClaim $claim, string $requestHash): OnlineTimeClaim
    {
        if (! hash_equals($claim->request_hash, $requestHash)) {
            throw new CreditEarningRejected(CreditEarningBlockReason::IdempotencyConflict, 409);
        }

        return $claim;
    }
}
