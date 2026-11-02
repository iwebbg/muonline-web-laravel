<?php

namespace App\Market\Actions;

use App\Economy\Actions\AdjustWalletBalance;
use App\Economy\Exceptions\WalletAdjustmentRejected;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingBlockReason;
use App\Market\MarketListingStatus;
use App\Market\MarketSaleType;
use App\Models\AuctionBid;
use App\Models\MarketListing;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

/**
 * Settles one expired auction into seller return or winning-bid delivery.
 */
final readonly class SettleExpiredAuction
{
    /**
     * Creates the settlement action with wallet and warehouse boundaries.
     */
    public function __construct(
        private AdjustWalletBalance $adjustWalletBalance,
        private SynchronizeMarketListing $synchronizeMarketListing,
    ) {}

    /**
     * Credits the seller from the winning hold exactly once and queues delivery.
     */
    public function execute(int $listingId): MarketListing
    {
        $listing = DB::transaction(function () use ($listingId): MarketListing {
            $lockedListing = MarketListing::query()
                ->whereKey($listingId)
                ->lockForUpdate()
                ->first();

            if (
                ! $lockedListing instanceof MarketListing
                || $lockedListing->sale_type !== MarketSaleType::Auction
            ) {
                throw new MarketListingRejected(MarketListingBlockReason::AuctionUnavailable);
            }

            if ($lockedListing->status !== MarketListingStatus::Active) {
                return $lockedListing;
            }

            if ($lockedListing->expires_at === null || $lockedListing->expires_at->isFuture()) {
                throw new MarketListingRejected(MarketListingBlockReason::AuctionUnavailable);
            }

            if ($lockedListing->highest_bid_id === null) {
                $lockedListing->update([
                    'status' => MarketListingStatus::ReturnPending,
                    'settled_at' => now(),
                ]);

                return $lockedListing;
            }

            $winningBid = AuctionBid::query()
                ->whereKey($lockedListing->highest_bid_id)
                ->lockForUpdate()
                ->first();

            if (! $winningBid instanceof AuctionBid) {
                throw new MarketListingRejected(MarketListingBlockReason::AuctionUnavailable);
            }

            try {
                $sellerTransaction = $this->adjustWalletBalance->execute(
                    accountId: $lockedListing->seller_account_id,
                    currency: WalletCurrency::Credits,
                    type: WalletTransactionType::Sale,
                    amount: $winningBid->amount_credits,
                    reason: 'Auction sale: '.$lockedListing->public_id,
                    administratorId: null,
                    idempotencyKey: $this->sellerWalletKey($lockedListing->public_id),
                );
            } catch (WalletAdjustmentRejected) {
                throw new MarketListingRejected(MarketListingBlockReason::AuctionUnavailable);
            }

            $lockedListing->update([
                'buyer_account_id' => $winningBid->bidder_account_id,
                'buyer_wallet_transaction_id' => $winningBid->hold_wallet_transaction_id,
                'seller_wallet_transaction_id' => $sellerTransaction->getKey(),
                'status' => MarketListingStatus::DeliveryPending,
                'purchased_at' => now(),
                'settled_at' => now(),
                'last_sync_error' => null,
            ]);

            return $lockedListing;
        }, attempts: 3);

        $this->synchronizeMarketListing->execute($listing);

        return $listing->refresh();
    }

    /**
     * Derives the stable seller-credit UUID for this one auction settlement.
     */
    private function sellerWalletKey(string $publicId): string
    {
        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            'muonline-auction-settlement:'.$publicId,
        )->toString();
    }
}
