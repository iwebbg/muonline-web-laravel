<?php

namespace App\Market\Actions;

use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingBlockReason;
use App\Market\MarketListingStatus;
use App\Models\MarketListing;
use Illuminate\Support\Facades\DB;

/**
 * Removes an unsold listing from sale and returns its original item safely.
 */
final readonly class ReturnMarketListing
{
    /**
     * Creates the return action with its retryable game synchronizer.
     */
    public function __construct(
        private SynchronizeMarketListing $synchronizeMarketListing,
    ) {}

    /**
     * Starts one owner-scoped return and immediately attempts warehouse delivery.
     */
    public function execute(string $accountId, int $listingId): MarketListing
    {
        $listing = DB::transaction(function () use ($accountId, $listingId): MarketListing {
            $lockedListing = MarketListing::query()
                ->whereKey($listingId)
                ->where('seller_account_id', $accountId)
                ->lockForUpdate()
                ->first();

            if (! $lockedListing instanceof MarketListing) {
                throw new MarketListingRejected(MarketListingBlockReason::ListingUnavailable);
            }

            if ($lockedListing->status === MarketListingStatus::Returned) {
                return $lockedListing;
            }

            if (! in_array(
                $lockedListing->status,
                [MarketListingStatus::Active, MarketListingStatus::ReturnPending],
                true,
            )) {
                throw new MarketListingRejected(MarketListingBlockReason::ListingUnavailable);
            }

            $lockedListing->update([
                'status' => MarketListingStatus::ReturnPending,
            ]);

            return $lockedListing;
        }, attempts: 3);

        $this->synchronizeMarketListing->execute($listing);

        return $listing->refresh();
    }
}
