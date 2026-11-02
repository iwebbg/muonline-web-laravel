<?php

namespace App\Console\Commands;

use App\Market\Actions\SynchronizeMarketListing;
use App\Market\MarketListingStatus;
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
    public function handle(SynchronizeMarketListing $synchronizeMarketListing): int
    {
        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            $this->components->warn('Market escrow synchronization skipped: game writes are disabled.');

            return self::SUCCESS;
        }

        $synchronized = 0;
        $pending = 0;
        $listings = MarketListing::query()
            ->whereIn('status', [
                MarketListingStatus::Extracting->value,
                MarketListingStatus::DeliveryPending->value,
                MarketListingStatus::ReturnPending->value,
            ])
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
