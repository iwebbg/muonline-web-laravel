<?php

namespace App\Console\Commands;

use App\Market\Actions\SettleExpiredAuction;
use App\Market\Actions\SynchronizeMarketListing;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingStatus;
use App\Market\MarketSaleType;
use App\Models\MarketListing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Retries interrupted market extractions, deliveries and returns in bounded batches.
 */
#[Signature('market:sync-escrow')]
#[Description('Retry pending MuOnline market escrow extraction, delivery and return writes.')]
final class SynchronizeMarketEscrow extends Command
{
    /**
     * Executes one bounded synchronization pass.
     */
    public function handle(
        SynchronizeMarketListing $synchronizeMarketListing,
        SettleExpiredAuction $settleExpiredAuction,
    ): int {
        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            $this->components->warn('Market escrow synchronization skipped: game writes are disabled.');

            return self::SUCCESS;
        }

        $synchronized = 0;
        $pending = 0;
        $processedAuctionIds = [];
        $expiredListings = MarketListing::query()
            ->where('status', MarketListingStatus::Active)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($expiredListings as $expiredListing) {
            if ($expiredListing->sale_type === MarketSaleType::Auction) {
                $processedAuctionIds[] = $expiredListing->getKey();
                try {
                    $settledListing = $settleExpiredAuction->execute(
                        (int) $expiredListing->getKey(),
                    );

                    if (in_array(
                        $settledListing->status,
                        [MarketListingStatus::Delivered, MarketListingStatus::Returned],
                        true,
                    )) {
                        $synchronized++;
                    } else {
                        $pending++;
                    }
                } catch (MarketListingRejected) {
                    $pending++;
                }

                continue;
            }

            MarketListing::query()
                ->whereKey($expiredListing->getKey())
                ->where('status', MarketListingStatus::Active)
                ->update([
                    'status' => MarketListingStatus::ReturnPending,
                ]);
        }

        $listings = MarketListing::query()
            ->whereIn('status', [
                MarketListingStatus::Extracting->value,
                MarketListingStatus::DeliveryPending->value,
                MarketListingStatus::ReturnPending->value,
            ])
            ->when(
                $processedAuctionIds !== [],
                fn ($query) => $query->whereNotIn('id', $processedAuctionIds),
            )
            ->orderBy('id')
            ->limit(100)
            ->get();

        foreach ($listings as $listing) {
            if ($synchronizeMarketListing->execute($listing)) {
                $synchronized++;
            } else {
                $pending++;
            }
        }

        $this->components->info(
            "Market escrow synchronization complete: {$synchronized} synced, {$pending} pending retry.",
        );

        return self::SUCCESS;
    }
}
