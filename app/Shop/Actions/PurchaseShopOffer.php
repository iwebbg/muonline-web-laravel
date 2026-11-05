<?php

namespace App\Shop\Actions;

use App\Economy\Actions\AdjustWalletBalance;
use App\Economy\Exceptions\WalletAdjustmentRejected;
use App\Economy\WalletAdjustmentBlockReason;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Models\AccountReward;
use App\Models\ShopOffer;
use App\Models\ShopPurchase;
use App\Shop\AccountRewardStatus;
use App\Shop\Exceptions\ShopPurchaseRejected;
use App\Shop\MysteryBoxRewardMode;
use App\Shop\RewardSource;
use App\Shop\ShopOfferStatus;
use App\Shop\ShopOfferType;
use App\Shop\ShopPurchaseBlockReason;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * Charges Credits and creates durable reward ownership in one transaction.
 */
final readonly class PurchaseShopOffer
{
    /**
     * Creates the purchase action with the shared Credits ledger boundary.
     */
    public function __construct(
        private AdjustWalletBalance $adjustWalletBalance,
        private FulfillAccountReward $fulfillAccountReward,
    ) {}

    /**
     * Purchases one currently available offer exactly once.
     *
     * @throws ShopPurchaseRejected When availability, balance or replay checks fail.
     */
    public function execute(string $accountId, ShopOffer $offer, string $idempotencyKey): AccountReward
    {
        $requestHash = hash('sha256', $accountId.'|'.$offer->public_id);
        $existingPurchase = ShopPurchase::query()
            ->with('reward')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingPurchase instanceof ShopPurchase) {
            return $this->resolveExisting($existingPurchase, $requestHash);
        }

        try {
            $reward = DB::transaction(function () use ($accountId, $offer, $idempotencyKey, $requestHash): AccountReward {
                $lockedOffer = ShopOffer::query()
                    ->with('items')
                    ->whereKey($offer->getKey())
                    ->lockForUpdate()
                    ->first();

                if (! $lockedOffer instanceof ShopOffer || ! $this->isAvailable($lockedOffer)) {
                    throw new ShopPurchaseRejected(ShopPurchaseBlockReason::OfferUnavailable);
                }

                $rewardPool = $lockedOffer->items
                    ->map(static fn ($item): array => $item->snapshot())
                    ->values()
                    ->all();

                if ($rewardPool === []) {
                    throw new ShopPurchaseRejected(ShopPurchaseBlockReason::OfferUnavailable);
                }

                try {
                    $walletTransaction = $this->adjustWalletBalance->execute(
                        accountId: $accountId,
                        currency: WalletCurrency::Credits,
                        type: WalletTransactionType::Purchase,
                        amount: -$lockedOffer->price_credits,
                        reason: 'Web Shop purchase: '.$lockedOffer->public_id,
                        administratorId: null,
                        idempotencyKey: Uuid::uuid5(Uuid::NAMESPACE_URL, 'muonline-shop:'.$idempotencyKey)->toString(),
                    );
                } catch (WalletAdjustmentRejected $exception) {
                    $reason = $exception->reason === WalletAdjustmentBlockReason::InsufficientBalance
                        ? ShopPurchaseBlockReason::InsufficientCredits
                        : ShopPurchaseBlockReason::OfferUnavailable;

                    throw new ShopPurchaseRejected($reason);
                }

                $offerSnapshot = [
                    'public_id' => $lockedOffer->public_id,
                    'type' => $lockedOffer->type->value,
                    'mystery_box_reward_mode' => $lockedOffer->mystery_box_reward_mode->value,
                    'class_scope' => $lockedOffer->class_scope->value,
                    'title_en' => $lockedOffer->title_en,
                    'title_bg' => $lockedOffer->title_bg,
                    'description_en' => $lockedOffer->description_en,
                    'description_bg' => $lockedOffer->description_bg,
                    'price_credits' => $lockedOffer->price_credits,
                    'items' => $rewardPool,
                ];
                $purchase = ShopPurchase::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'account_id' => $accountId,
                    'shop_offer_id' => $lockedOffer->getKey(),
                    'offer_snapshot' => $offerSnapshot,
                    'price_credits' => $lockedOffer->price_credits,
                    'wallet_transaction_id' => $walletTransaction->getKey(),
                ]);
                $isMysteryBox = $lockedOffer->type === ShopOfferType::MysteryBox;

                return AccountReward::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'account_id' => $accountId,
                    'shop_purchase_id' => $purchase->getKey(),
                    'shop_offer_id' => $lockedOffer->getKey(),
                    'source' => RewardSource::ShopPurchase,
                    'source_reference' => $purchase->public_id,
                    'status' => $isMysteryBox ? AccountRewardStatus::Sealed : AccountRewardStatus::Ready,
                    'reward_type' => $lockedOffer->type,
                    'mystery_box_reward_mode' => $isMysteryBox
                        ? $lockedOffer->mystery_box_reward_mode
                        : MysteryBoxRewardMode::OneRandom,
                    'class_scope' => $lockedOffer->class_scope,
                    'title_en' => $lockedOffer->title_en,
                    'title_bg' => $lockedOffer->title_bg,
                    'description_en' => $lockedOffer->description_en,
                    'description_bg' => $lockedOffer->description_bg,
                    'reward_pool_snapshot' => $rewardPool,
                    'selected_item_snapshot' => $isMysteryBox ? null : $rewardPool[0],
                    'opened_at' => $isMysteryBox ? null : now(),
                ]);
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existingPurchase = ShopPurchase::query()
                ->with('reward')
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingPurchase instanceof ShopPurchase) {
                return $this->resolveExisting($existingPurchase, $requestHash);
            }

            throw $exception;
        }

        if ($reward->status === AccountRewardStatus::Ready) {
            $this->fulfillAccountReward->execute($reward);
        }

        return $reward->refresh();
    }

    /**
     * Reports whether a locked offer remains published inside its sale window.
     */
    private function isAvailable(ShopOffer $offer): bool
    {
        return $offer->status === ShopOfferStatus::Published
            && ($offer->available_from === null || $offer->available_from->isPast())
            && ($offer->available_until === null || $offer->available_until->isFuture());
    }

    /**
     * Returns an identical replay or rejects one key reused for another offer.
     */
    private function resolveExisting(ShopPurchase $purchase, string $requestHash): AccountReward
    {
        if (! hash_equals($purchase->request_hash, $requestHash)) {
            throw new ShopPurchaseRejected(ShopPurchaseBlockReason::IdempotencyConflict);
        }

        $reward = $purchase->reward;

        if (! $reward instanceof AccountReward) {
            throw new ShopPurchaseRejected(ShopPurchaseBlockReason::OfferUnavailable);
        }

        return $reward;
    }
}
