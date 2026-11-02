<?php

namespace App\Vip\Actions;

use App\Economy\Actions\AdjustWalletBalance;
use App\Economy\Exceptions\WalletAdjustmentRejected;
use App\Economy\WalletAdjustmentBlockReason;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Game\Contracts\AccountReadRepository;
use App\Models\VipEntitlement;
use App\Models\VipPlan;
use App\Models\VipPurchase;
use App\Vip\Exceptions\VipPurchaseRejected;
use App\Vip\VipPurchaseBlockReason;
use App\Vip\VipSyncStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Purchases and synchronizes character VIP time with an atomic Credits debit.
 */
final readonly class PurchaseCharacterVip
{
    /**
     * Creates the purchase action with ownership, wallet and synchronization boundaries.
     */
    public function __construct(
        private AccountReadRepository $accountReadRepository,
        private AdjustWalletBalance $adjustWalletBalance,
        private SynchronizeVipEntitlement $synchronizeVipEntitlement,
    ) {}

    /**
     * Extends one offline owned character's VIP time exactly once.
     *
     * The application transaction is the source of truth. Game synchronization is
     * attempted after commit and remains retryable when the game database is busy.
     *
     * @throws VipPurchaseRejected When policy, ownership, balance or replay checks fail.
     */
    public function execute(
        string $accountId,
        string $characterName,
        int $planId,
        string $idempotencyKey,
    ): VipPurchase {
        $requestHash = $this->requestHash(
            accountId: $accountId,
            characterName: $characterName,
            planId: $planId,
        );
        $existingPurchase = VipPurchase::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingPurchase instanceof VipPurchase) {
            return $this->resolveExisting($existingPurchase, $requestHash);
        }

        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            throw new VipPurchaseRejected(VipPurchaseBlockReason::WritesDisabled);
        }

        $plan = VipPlan::query()
            ->available()
            ->find($planId);

        if (! $plan instanceof VipPlan) {
            throw new VipPurchaseRejected(VipPurchaseBlockReason::PlanUnavailable);
        }

        $character = $this->accountReadRepository->characterFor($accountId, $characterName);

        if ($character === null) {
            throw new VipPurchaseRejected(VipPurchaseBlockReason::CharacterNotFound);
        }

        if ($character->online) {
            throw new VipPurchaseRejected(VipPurchaseBlockReason::CharacterOnline);
        }

        try {
            $purchase = DB::transaction(function () use (
                $accountId,
                $characterName,
                $plan,
                $idempotencyKey,
                $requestHash,
            ): VipPurchase {
                $purchaseStartsAt = now();

                VipEntitlement::query()->insertOrIgnore([
                    'account_id' => $accountId,
                    'character_name' => $characterName,
                    'vip_plan_id' => $plan->getKey(),
                    'starts_at' => $purchaseStartsAt,
                    'expires_at' => $purchaseStartsAt,
                    'sync_status' => VipSyncStatus::Pending->value,
                    'sync_attempts' => 0,
                    'created_at' => $purchaseStartsAt,
                    'updated_at' => $purchaseStartsAt,
                ]);

                $entitlement = VipEntitlement::query()
                    ->where('account_id', $accountId)
                    ->where('character_name', $characterName)
                    ->lockForUpdate()
                    ->firstOrFail();
                $previousExpiresAt = $entitlement->expires_at->isAfter($purchaseStartsAt)
                    ? $entitlement->expires_at
                    : null;
                $extensionBase = $previousExpiresAt ?? $purchaseStartsAt;
                $newExpiresAt = $extensionBase->addDays($plan->duration_days);

                try {
                    $walletTransaction = $this->adjustWalletBalance->execute(
                        accountId: $accountId,
                        currency: WalletCurrency::Credits,
                        type: WalletTransactionType::Purchase,
                        amount: -$plan->price_credits,
                        reason: 'Character VIP purchase: '.$plan->slug,
                        administratorId: null,
                        idempotencyKey: $idempotencyKey,
                    );
                } catch (WalletAdjustmentRejected $exception) {
                    $this->rethrowWalletRejection($exception);
                }

                $entitlement->update([
                    'vip_plan_id' => $plan->getKey(),
                    'starts_at' => $purchaseStartsAt,
                    'expires_at' => $newExpiresAt,
                    'sync_status' => VipSyncStatus::Pending,
                    'last_sync_error' => null,
                ]);

                return VipPurchase::query()->create([
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'vip_entitlement_id' => $entitlement->getKey(),
                    'vip_plan_id' => $plan->getKey(),
                    'wallet_transaction_id' => $walletTransaction->getKey(),
                    'account_id' => $accountId,
                    'character_name' => $characterName,
                    'plan_name' => $plan->name_en,
                    'duration_days' => $plan->duration_days,
                    'price_credits' => $plan->price_credits,
                    'previous_expires_at' => $previousExpiresAt,
                    'starts_at' => $purchaseStartsAt,
                    'expires_at' => $newExpiresAt,
                ]);
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existingPurchase = VipPurchase::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingPurchase instanceof VipPurchase) {
                return $this->resolveExisting($existingPurchase, $requestHash);
            }

            throw $exception;
        }

        $entitlement = $purchase->entitlement()->firstOrFail();
        $this->synchronizeVipEntitlement->execute($entitlement);

        return $purchase->refresh();
    }

    /**
     * Returns an identical purchase replay or rejects a conflicting request key.
     */
    private function resolveExisting(
        VipPurchase $purchase,
        string $requestHash,
    ): VipPurchase {
        if (! hash_equals($purchase->request_hash, $requestHash)) {
            throw new VipPurchaseRejected(VipPurchaseBlockReason::IdempotencyConflict);
        }

        return $purchase;
    }

    /**
     * Maps wallet boundary failures to the smaller public VIP purchase vocabulary.
     */
    private function rethrowWalletRejection(WalletAdjustmentRejected $exception): never
    {
        $reason = match ($exception->reason) {
            WalletAdjustmentBlockReason::InsufficientBalance => VipPurchaseBlockReason::InsufficientCredits,
            WalletAdjustmentBlockReason::IdempotencyConflict => VipPurchaseBlockReason::IdempotencyConflict,
            default => VipPurchaseBlockReason::CharacterNotFound,
        };

        throw new VipPurchaseRejected($reason);
    }

    /**
     * Produces the deterministic fingerprint bound to one purchase request key.
     */
    private function requestHash(
        string $accountId,
        string $characterName,
        int $planId,
    ): string {
        $payload = json_encode([
            'account_id' => $accountId,
            'character_name' => $characterName,
            'plan_id' => $planId,
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $payload);
    }
}
