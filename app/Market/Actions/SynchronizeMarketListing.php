<?php

namespace App\Market\Actions;

use App\Game\Contracts\MarketItemEscrowRepository;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingBlockReason;
use App\Market\MarketListingStatus;
use App\Models\MarketListing;
use Throwable;

/**
 * Projects one recoverable market escrow transition into the game warehouse.
 */
final readonly class SynchronizeMarketListing
{
    /**
     * Creates the synchronizer with the version-aware warehouse writer.
     */
    public function __construct(
        private MarketItemEscrowRepository $escrowRepository,
    ) {}

    /**
     * Attempts extraction or return while preserving retryable application state.
     */
    public function execute(MarketListing $listing): bool
    {
        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            $this->recordFailure($listing, MarketListingBlockReason::WritesDisabled->value);

            return false;
        }

        try {
            if ($listing->status === MarketListingStatus::Extracting) {
                $this->escrowRepository->extract(
                    accountId: $listing->seller_account_id,
                    vault: $listing->source_vault,
                    slot: $listing->source_slot,
                    expectedBinary: $listing->item_binary,
                );
                $listing->forceFill([
                    'status' => MarketListingStatus::Active,
                    'activated_at' => now(),
                    'last_sync_error' => null,
                ]);
            } elseif ($listing->status === MarketListingStatus::ReturnPending) {
                $snapshot = $listing->item_snapshot;
                $this->escrowRepository->deposit(
                    accountId: $listing->seller_account_id,
                    itemBinary: $listing->item_binary,
                    width: (int) ($snapshot['width'] ?? 1),
                    height: (int) ($snapshot['height'] ?? 1),
                );
                $listing->forceFill([
                    'status' => MarketListingStatus::Returned,
                    'escrow_key' => null,
                    'returned_at' => now(),
                    'last_sync_error' => null,
                ]);
            } else {
                return true;
            }
        } catch (MarketListingRejected $exception) {
            $terminalExtractionFailure = $listing->status === MarketListingStatus::Extracting
                && in_array(
                    $exception->reason,
                    [
                        MarketListingBlockReason::ItemChanged,
                        MarketListingBlockReason::ItemNotFound,
                        MarketListingBlockReason::WarehouseNotFound,
                    ],
                    true,
                );

            if ($terminalExtractionFailure) {
                $listing->forceFill([
                    'status' => MarketListingStatus::Failed,
                    'escrow_key' => null,
                ]);
            }

            $this->recordFailure($listing, $exception->reason->value);

            return false;
        } catch (Throwable) {
            $this->recordFailure($listing, 'game_sync_failed');

            return false;
        }

        $listing->forceFill([
            'sync_attempts' => $listing->sync_attempts + 1,
            'last_sync_attempt_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Records one safe synchronization failure without exposing infrastructure details.
     */
    private function recordFailure(MarketListing $listing, string $reason): void
    {
        $listing->forceFill([
            'sync_attempts' => $listing->sync_attempts + 1,
            'last_sync_attempt_at' => now(),
            'last_sync_error' => $reason,
        ])->save();
    }
}
