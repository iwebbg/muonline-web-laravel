<?php

namespace App\Market\Actions;

use App\Economy\Actions\AdjustWalletBalance;
use App\Economy\Exceptions\WalletAdjustmentRejected;
use App\Economy\WalletAdjustmentBlockReason;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingBlockReason;
use App\Market\MarketListingStatus;
use App\Models\MarketListing;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Transfers one active escrow listing and its Credits atomically to a buyer.
 */
final readonly class PurchaseMarketListing
{
    /**
     * Creates the purchase action with wallet and game-delivery boundaries.
     */
    public function __construct(
        private AdjustWalletBalance $adjustWalletBalance,
        private SynchronizeMarketListing $synchronizeMarketListing,
    ) {}

    /**
     * Purchases one listing exactly once and attempts immediate warehouse delivery.
     *
     * @throws MarketListingRejected When availability, balance or replay checks fail.
     */
    public function execute(
        string $buyerAccountId,
        int $listingId,
        string $idempotencyKey,
    ): MarketListing {
        $requestHash = $this->requestHash($buyerAccountId, $listingId);
        $existingListing = MarketListing::query()
            ->where('purchase_idempotency_key', $idempotencyKey)
            ->first();

        if ($existingListing instanceof MarketListing) {
            return $this->resolveExisting($existingListing, $requestHash);
        }

        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            throw new MarketListingRejected(MarketListingBlockReason::WritesDisabled);
        }

        try {
            $listing = DB::transaction(function () use (
                $buyerAccountId,
                $listingId,
                $idempotencyKey,
                $requestHash,
            ): MarketListing {
                $lockedListing = MarketListing::query()
                    ->whereKey($listingId)
                    ->lockForUpdate()
                    ->first();

                if (! $lockedListing instanceof MarketListing) {
                    throw new MarketListingRejected(MarketListingBlockReason::ListingUnavailable);
                }

                if ($lockedListing->purchase_idempotency_key === $idempotencyKey) {
                    if (
                        ! is_string($lockedListing->purchase_request_hash)
                        || ! hash_equals($lockedListing->purchase_request_hash, $requestHash)
                    ) {
                        throw new MarketListingRejected(MarketListingBlockReason::IdempotencyConflict);
                    }

                    return $lockedListing;
                }

                if ($lockedListing->status !== MarketListingStatus::Active) {
                    throw new MarketListingRejected(MarketListingBlockReason::ListingUnavailable);
                }

                if ($lockedListing->expires_at?->isPast()) {
                    throw new MarketListingRejected(MarketListingBlockReason::ListingUnavailable);
                }

                if ($lockedListing->seller_account_id === $buyerAccountId) {
                    throw new MarketListingRejected(MarketListingBlockReason::OwnListing);
                }

                try {
                    $buyerTransaction = $this->adjustWalletBalance->execute(
                        accountId: $buyerAccountId,
                        currency: WalletCurrency::Credits,
                        type: WalletTransactionType::Purchase,
                        amount: -$lockedListing->price_credits,
                        reason: 'Market purchase: '.$lockedListing->public_id,
                        administratorId: null,
                        idempotencyKey: $this->walletKey($idempotencyKey, 'buyer'),
                    );
                    $sellerTransaction = $this->adjustWalletBalance->execute(
                        accountId: $lockedListing->seller_account_id,
                        currency: WalletCurrency::Credits,
                        type: WalletTransactionType::Sale,
                        amount: $lockedListing->price_credits,
                        reason: 'Market sale: '.$lockedListing->public_id,
                        administratorId: null,
                        idempotencyKey: $this->walletKey($idempotencyKey, 'seller'),
                    );
                } catch (WalletAdjustmentRejected $exception) {
                    $this->rethrowWalletRejection($exception);
                }

                $lockedListing->update([
                    'buyer_account_id' => $buyerAccountId,
                    'purchase_idempotency_key' => $idempotencyKey,
                    'purchase_request_hash' => $requestHash,
                    'buyer_wallet_transaction_id' => $buyerTransaction->getKey(),
                    'seller_wallet_transaction_id' => $sellerTransaction->getKey(),
                    'status' => MarketListingStatus::DeliveryPending,
                    'purchased_at' => now(),
                    'last_sync_error' => null,
                ]);

                return $lockedListing;
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existingListing = MarketListing::query()
                ->where('purchase_idempotency_key', $idempotencyKey)
                ->first();

            if ($existingListing instanceof MarketListing) {
                return $this->resolveExisting($existingListing, $requestHash);
            }

            throw $exception;
        }

        $this->synchronizeMarketListing->execute($listing);

        return $listing->refresh();
    }

    /**
     * Returns an identical replay or rejects one key reused for another purchase.
     */
    private function resolveExisting(MarketListing $listing, string $requestHash): MarketListing
    {
        if (
            ! is_string($listing->purchase_request_hash)
            || ! hash_equals($listing->purchase_request_hash, $requestHash)
        ) {
            throw new MarketListingRejected(MarketListingBlockReason::IdempotencyConflict);
        }

        if ($listing->status === MarketListingStatus::DeliveryPending) {
            $this->synchronizeMarketListing->execute($listing);
        }

        return $listing->refresh();
    }

    /**
     * Maps wallet failures to the smaller public market vocabulary.
     */
    private function rethrowWalletRejection(WalletAdjustmentRejected $exception): never
    {
        $reason = match ($exception->reason) {
            WalletAdjustmentBlockReason::InsufficientBalance => MarketListingBlockReason::InsufficientCredits,
            WalletAdjustmentBlockReason::IdempotencyConflict => MarketListingBlockReason::IdempotencyConflict,
            default => MarketListingBlockReason::ListingUnavailable,
        };

        throw new MarketListingRejected($reason);
    }

    /**
     * Derives a stable ledger UUID without sharing the public purchase replay key.
     */
    private function walletKey(string $idempotencyKey, string $side): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'muonline-market:'.$idempotencyKey.':'.$side)->toString();
    }

    /**
     * Produces the deterministic fingerprint bound to one purchase request key.
     */
    private function requestHash(string $buyerAccountId, int $listingId): string
    {
        $payload = json_encode([
            'buyer_account_id' => $buyerAccountId,
            'listing_id' => $listingId,
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $payload);
    }
}
