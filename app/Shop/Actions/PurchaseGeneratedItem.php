<?php

namespace App\Shop\Actions;

use App\Economy\Actions\AdjustWalletBalance;
use App\Economy\Exceptions\WalletAdjustmentRejected;
use App\Economy\WalletAdjustmentBlockReason;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Game\Contracts\ItemGeneratorCatalog;
use App\Models\AccountReward;
use App\Models\ShopPurchase;
use App\Shop\AccountRewardStatus;
use App\Shop\Exceptions\ShopPurchaseRejected;
use App\Shop\RewardSource;
use App\Shop\ShopClassScope;
use App\Shop\ShopOfferType;
use App\Shop\ShopPurchaseBlockReason;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * Charges the server-calculated Credits price and creates generated-item ownership.
 */
final readonly class PurchaseGeneratedItem
{
    /**
     * Creates the action with pricing, ledger and delivery boundaries.
     */
    public function __construct(
        private ItemGeneratorCatalog $catalog,
        private AdjustWalletBalance $adjustWalletBalance,
        private FulfillAccountReward $fulfillAccountReward,
    ) {}

    /**
     * Purchases one exact catalog-backed item configuration exactly once.
     *
     * @param  array{group: int, index: int, level: int, durability: int, option_level: int, has_skill: bool, has_luck: bool, excellent_mask: int, ancient_option: int}  $options
     *
     * @throws ShopPurchaseRejected When configuration, balance or replay checks fail.
     */
    public function execute(
        string $accountId,
        array $options,
        string $idempotencyKey,
    ): AccountReward {
        $quote = $this->catalog->quote($options);

        if ($quote === null) {
            throw new ShopPurchaseRejected(ShopPurchaseBlockReason::OfferUnavailable);
        }

        $requestHash = hash('sha256', $accountId.'|'.json_encode($options, JSON_THROW_ON_ERROR));
        $existingPurchase = ShopPurchase::query()
            ->with('reward')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingPurchase instanceof ShopPurchase) {
            return $this->resolveExisting($existingPurchase, $requestHash);
        }

        try {
            $reward = DB::transaction(function () use (
                $accountId,
                $idempotencyKey,
                $requestHash,
                $quote,
            ): AccountReward {
                try {
                    $walletTransaction = $this->adjustWalletBalance->execute(
                        accountId: $accountId,
                        currency: WalletCurrency::Credits,
                        type: WalletTransactionType::Purchase,
                        amount: -$quote->priceCredits,
                        reason: 'Item Generator purchase: '.$quote->itemSnapshot['item_code'],
                        administratorId: null,
                        idempotencyKey: Uuid::uuid5(
                            Uuid::NAMESPACE_URL,
                            'muonline-item-generator:'.$idempotencyKey,
                        )->toString(),
                    );
                } catch (WalletAdjustmentRejected $exception) {
                    $reason = $exception->reason === WalletAdjustmentBlockReason::InsufficientBalance
                        ? ShopPurchaseBlockReason::InsufficientCredits
                        : ShopPurchaseBlockReason::OfferUnavailable;

                    throw new ShopPurchaseRejected($reason);
                }

                $offerSnapshot = [
                    'public_id' => null,
                    'type' => 'item_generator',
                    'class_scope' => ShopClassScope::All->value,
                    'title_en' => $quote->itemSnapshot['item_name'],
                    'title_bg' => $quote->itemSnapshot['item_name'],
                    'description_en' => null,
                    'description_bg' => null,
                    'price_credits' => $quote->priceCredits,
                    'price_breakdown' => $quote->priceBreakdown,
                    'items' => [$quote->itemSnapshot],
                ];
                $purchase = ShopPurchase::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'account_id' => $accountId,
                    'shop_offer_id' => null,
                    'offer_snapshot' => $offerSnapshot,
                    'price_credits' => $quote->priceCredits,
                    'wallet_transaction_id' => $walletTransaction->getKey(),
                ]);

                return AccountReward::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'account_id' => $accountId,
                    'shop_purchase_id' => $purchase->getKey(),
                    'shop_offer_id' => null,
                    'source' => RewardSource::ShopPurchase,
                    'source_reference' => $purchase->public_id,
                    'status' => AccountRewardStatus::Ready,
                    'reward_type' => ShopOfferType::Item,
                    'class_scope' => ShopClassScope::All,
                    'title_en' => $quote->itemSnapshot['item_name'],
                    'title_bg' => $quote->itemSnapshot['item_name'],
                    'description_en' => null,
                    'description_bg' => null,
                    'reward_pool_snapshot' => [$quote->itemSnapshot],
                    'selected_item_snapshot' => $quote->itemSnapshot,
                    'opened_at' => now(),
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

        $this->fulfillAccountReward->execute($reward);

        return $reward->refresh();
    }

    /**
     * Returns an identical replay or rejects one key reused for another build.
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
