<?php

namespace App\Economy\Actions;

use App\Economy\Exceptions\NativePurchaseRejected;
use App\Economy\Exceptions\WalletAdjustmentRejected;
use App\Economy\NativeAccountMutation;
use App\Economy\NativeCurrency;
use App\Economy\NativePurchaseBlockReason;
use App\Economy\NativePurchaseKind;
use App\Economy\WalletAdjustmentBlockReason;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Game\Contracts\NativeAccountServiceRepository;
use App\Game\Operations\CharacterOperationStatus;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Models\GameCurrencyExchangeRate;
use App\Models\NativeAccountPurchase;
use App\Models\VipPlan;
use App\Modules\ModuleManager;
use App\Modules\ServerModule;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Purchases native coins or account VIP with captured prices, durable receipts and explicit recovery.
 */
final readonly class PurchaseNativeAccountService
{
    /**
     * Separates application Credits accounting from native game mutations and module settings.
     */
    public function __construct(
        private NativeAccountServiceRepository $repository,
        private AdjustWalletBalance $wallet,
        private GameVersionProfile $profile,
        private ModuleManager $modules,
    ) {}

    /**
     * Exchanges an integer number of rate units for one exact native currency grant.
     */
    public function exchange(string $accountId, NativeCurrency $currency, int $units, ?string $characterName, string $key): NativeAccountPurchase
    {
        $this->ensureAvailable(NativePurchaseKind::CurrencyExchange);
        $characterName = $currency === NativeCurrency::Ruud ? $characterName : null;
        $hash = $this->fingerprint($accountId, NativePurchaseKind::CurrencyExchange, [$currency->value, $units, $characterName]);
        $existing = $this->existing($key, $hash);
        if ($existing !== null) {
            return $this->complete($existing);
        }
        $rate = GameCurrencyExchangeRate::query()->where('game_profile_key', $this->profile->key())
            ->where('currency', $currency)->where('enabled', true)->first();
        if ($rate === null) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::OfferUnavailable);
        }
        if ($units < $rate->minimum_units || $units > $rate->maximum_units
            || $rate->credit_units < 1 || $rate->coin_units < 1 || $units * $rate->coin_units > 2147483647) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::InvalidQuantity);
        }
        $index = null;
        if ($currency === NativeCurrency::Ruud) {
            $index = $this->repository->characterIndexFor($accountId, $characterName ?? '');
            if ($index === null) {
                throw new NativePurchaseRejected(NativePurchaseBlockReason::CharacterNotFound);
            }
        }
        $mutation = new NativeAccountMutation(NativePurchaseKind::CurrencyExchange, $currency, $units * $rate->coin_units, $index);

        return $this->createAndComplete($accountId, $key, $hash, $rate->getKey(), $units, $units * $rate->credit_units,
            $characterName, $mutation, $rate->snapshot());
    }

    /**
     * Purchases one configured native account VIP plan without creating a character entitlement.
     */
    public function purchaseVip(string $accountId, int $planId, string $key): NativeAccountPurchase
    {
        $this->ensureAvailable(NativePurchaseKind::AccountVip);
        $hash = $this->fingerprint($accountId, NativePurchaseKind::AccountVip, [$planId]);
        $existing = $this->existing($key, $hash);
        if ($existing !== null) {
            return $this->complete($existing);
        }
        $plan = VipPlan::query()->available()->whereNotNull('native_account_level')->find($planId);
        if ($plan === null || ! in_array($plan->native_account_level, [1, 2, 3], true)
            || $plan->price_credits < 1 || $plan->duration_days < 1 || $plan->duration_days > 3650) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::OfferUnavailable);
        }
        $mutation = new NativeAccountMutation(kind: NativePurchaseKind::AccountVip, vipLevel: $plan->native_account_level, durationDays: $plan->duration_days);

        return $this->createAndComplete($accountId, $key, $hash, $planId, 1, $plan->price_credits, null, $mutation, [
            'plan_name' => $plan->name_en, 'native_account_level' => $plan->native_account_level,
            'duration_days' => $plan->duration_days, 'price_credits' => $plan->price_credits,
        ]);
    }

    /**
     * Resumes only an exact owned purchase using its original product and grant snapshot.
     */
    public function resume(string $accountId, NativeAccountPurchase $purchase): NativeAccountPurchase
    {
        if (! hash_equals($accountId, $purchase->account_id) || $purchase->game_profile_key !== $this->profile->key()) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::IdempotencyConflict);
        }
        $this->ensureAvailable($purchase->kind);

        return $this->complete($purchase);
    }

    /**
     * Captures the offer before charging and prevents replacing an uncertain purchase with a new key.
     *
     * @param  array<string, int|string|bool>  $settings
     */
    private function createAndComplete(
        string $accountId, string $key, string $hash, int $productId, int $units, int $cost,
        ?string $characterName, NativeAccountMutation $mutation, array $settings,
    ): NativeAccountPurchase {
        if ($cost < 1 || $cost > 9_000_000_000_000_000) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::InvalidQuantity);
        }
        if (NativeAccountPurchase::query()->where('account_id', $accountId)->where('game_profile_key', $this->profile->key())
            ->where('status', CharacterOperationStatus::Pending)->exists()) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::PurchasePending);
        }
        $purchase = NativeAccountPurchase::query()->firstOrCreate(['idempotency_key' => $key], [
            'request_hash' => $hash, 'game_profile_key' => $this->profile->key(), 'account_id' => $accountId,
            'kind' => $mutation->kind, 'product_id' => $productId, 'character_name' => $characterName,
            'units' => $units, 'credit_cost' => $cost, 'status' => CharacterOperationStatus::Pending,
            'mutation_snapshot' => $mutation->toArray(), 'settings_snapshot' => $settings,
        ]);
        if (! hash_equals($hash, $purchase->request_hash)) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::IdempotencyConflict);
        }

        return $this->complete($purchase);
    }

    /**
     * Charges once, applies one journaled grant and refunds only a definitive native rejection.
     */
    private function complete(NativeAccountPurchase $purchase): NativeAccountPurchase
    {
        if ($purchase->game_profile_key !== $this->profile->key()) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::IdempotencyConflict);
        }
        if ($purchase->status === CharacterOperationStatus::Completed) {
            return $purchase;
        }
        if ($purchase->status === CharacterOperationStatus::Failed) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::tryFrom($purchase->failure_code ?? '') ?? NativePurchaseBlockReason::PurchasePending);
        }
        $this->debit($purchase);
        try {
            $result = $this->repository->apply($purchase->account_id, NativeAccountMutation::fromArray($purchase->mutation_snapshot), 'native-account:'.$purchase->idempotency_key);
        } catch (NativePurchaseRejected $exception) {
            $this->refund($purchase);
            $purchase->update(['status' => CharacterOperationStatus::Failed, 'failure_code' => $exception->reason->value, 'completed_at' => now()]);
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw new NativePurchaseRejected(NativePurchaseBlockReason::PurchasePending);
        }
        try {
            $purchase->update([
                'status' => CharacterOperationStatus::Completed, 'before_snapshot' => $result->before,
                'after_snapshot' => $result->after, 'failure_code' => null, 'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            throw new NativePurchaseRejected(NativePurchaseBlockReason::PurchasePending);
        }

        return $purchase->refresh();
    }

    /**
     * Reuses the immutable wallet debit when response or audit writes were interrupted.
     */
    private function debit(NativeAccountPurchase $purchase): void
    {
        if ($purchase->wallet_transaction_id !== null) {
            return;
        }
        try {
            $debit = $this->wallet->execute($purchase->account_id, WalletCurrency::Credits, WalletTransactionType::Purchase,
                -$purchase->credit_cost, 'Native account purchase: '.$purchase->kind->value, null, $this->walletKey($purchase, 'debit'));
            $purchase->update(['wallet_transaction_id' => $debit->getKey()]);
        } catch (WalletAdjustmentRejected $exception) {
            $reason = $exception->reason === WalletAdjustmentBlockReason::InsufficientBalance
                ? NativePurchaseBlockReason::InsufficientCredits : NativePurchaseBlockReason::IdempotencyConflict;
            $purchase->update(['status' => CharacterOperationStatus::Failed, 'failure_code' => $reason->value, 'completed_at' => now()]);
            throw new NativePurchaseRejected($reason);
        }
    }

    /**
     * Returns the captured price once after a receipt-backed definitive rejection.
     */
    private function refund(NativeAccountPurchase $purchase): void
    {
        $refund = $this->wallet->execute($purchase->account_id, WalletCurrency::Credits, WalletTransactionType::Refund,
            $purchase->credit_cost, 'Native account purchase refund: '.$purchase->kind->value, null, $this->walletKey($purchase, 'refund'));
        $purchase->update(['refund_wallet_transaction_id' => $refund->getKey()]);
    }

    /**
     * Requires both global write switches and the requested module without changing older adapters.
     */
    private function ensureAvailable(NativePurchaseKind $kind): void
    {
        if (! config('game.writes_enabled', false) || config('database.connections.game.readonly', true)) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::WritesDisabled);
        }
        $module = $kind === NativePurchaseKind::CurrencyExchange ? ServerModule::GameCurrencyExchange : ServerModule::Vip;
        $supported = $kind === NativePurchaseKind::CurrencyExchange
            ? $this->profile->capabilities()->gameCurrencyExchange : $this->profile->capabilities()->accountVip;
        if (! $supported || ! $this->modules->enabled(ServerModule::Credits) || ! $this->modules->enabled($module)) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::ModuleDisabled);
        }
    }

    /**
     * Rejects key collisions before reading or writing another account's purchase.
     */
    private function existing(string $key, string $hash): ?NativeAccountPurchase
    {
        if (! Uuid::isValid($key)) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::IdempotencyConflict);
        }
        $purchase = NativeAccountPurchase::query()->where('idempotency_key', $key)->first();
        if ($purchase !== null && (! hash_equals($purchase->request_hash, $hash) || $purchase->game_profile_key !== $this->profile->key())) {
            throw new NativePurchaseRejected(NativePurchaseBlockReason::IdempotencyConflict);
        }

        return $purchase;
    }

    /**
     * Fingerprints user intent separately from mutable administrator prices.
     *
     * @param  list<string|int|null>  $intent
     */
    private function fingerprint(string $accountId, NativePurchaseKind $kind, array $intent): string
    {
        return hash('sha256', json_encode([$accountId, $this->profile->key(), $kind->value, $intent], JSON_THROW_ON_ERROR));
    }

    /**
     * Derives separate wallet ledger identities for the purchase debit and its possible refund.
     */
    private function walletKey(NativeAccountPurchase $purchase, string $direction): string
    {
        return hash('sha256', 'native-account:'.$purchase->idempotency_key.':'.$direction);
    }
}
