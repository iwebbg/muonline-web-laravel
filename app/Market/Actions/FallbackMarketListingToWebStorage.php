<?php

namespace App\Market\Actions;

use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingBlockReason;
use App\Market\MarketListingStatus;
use App\Market\MarketStorageDestination;
use App\Models\MarketListing;
use App\Models\WebStorageItem;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * Moves an undeliverable market escrow item into recoverable web storage.
 */
final readonly class FallbackMarketListingToWebStorage
{
    /**
     * Atomically transfers escrow custody and finalizes delivery or return.
     */
    public function execute(int $listingId): MarketListing
    {
        return DB::transaction(function () use ($listingId): MarketListing {
            $listing = MarketListing::query()
                ->whereKey($listingId)
                ->lockForUpdate()
                ->first();

            if (! $listing instanceof MarketListing) {
                throw new MarketListingRejected(MarketListingBlockReason::ListingUnavailable);
            }

            if (! in_array(
                $listing->status,
                [MarketListingStatus::DeliveryPending, MarketListingStatus::ReturnPending],
                true,
            )) {
                return $listing;
            }

            $accountId = $listing->status === MarketListingStatus::DeliveryPending
                ? $listing->buyer_account_id
                : $listing->seller_account_id;

            if (! is_string($accountId) || $accountId === '') {
                throw new MarketListingRejected(MarketListingBlockReason::ListingUnavailable);
            }

            $idempotencyKey = $this->idempotencyKey($listing);
            $requestHash = $this->requestHash($listing, $accountId);
            $storedItem = WebStorageItem::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if (! $storedItem instanceof WebStorageItem) {
                $storedItem = WebStorageItem::query()
                    ->where('escrow_key', $listing->item_fingerprint)
                    ->first();
            }

            if ($storedItem instanceof WebStorageItem) {
                $this->assertMatchingStoredItem(
                    storedItem: $storedItem,
                    listing: $listing,
                    accountId: $accountId,
                    requestHash: $requestHash,
                );
            } else {
                WebStorageItem::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => $requestHash,
                    'account_id' => $accountId,
                    'source_character_name' => $listing->source_character_name,
                    'source_vault' => $listing->source_vault,
                    'source_slot' => $listing->source_slot,
                    'item_binary' => $listing->item_binary,
                    'item_fingerprint' => $listing->item_fingerprint,
                    'escrow_key' => $listing->item_fingerprint,
                    'item_snapshot' => $listing->item_snapshot,
                    'status' => WebStorageItemStatus::Available,
                    'sync_attempts' => 0,
                    'stored_at' => now(),
                ]);
            }

            $terminalStatus = $listing->status === MarketListingStatus::DeliveryPending
                ? MarketListingStatus::Delivered
                : MarketListingStatus::Returned;
            $terminalTimestamp = now();
            $terminalValues = [
                'status' => $terminalStatus,
                'storage_destination' => MarketStorageDestination::WebVault,
                'escrow_key' => null,
                'sync_attempts' => $listing->sync_attempts + 1,
                'last_sync_attempt_at' => $terminalTimestamp,
                'last_sync_error' => null,
            ];

            if ($terminalStatus === MarketListingStatus::Delivered) {
                $terminalValues['delivered_at'] = $terminalTimestamp;
            } else {
                $terminalValues['returned_at'] = $terminalTimestamp;
            }

            $listing->forceFill($terminalValues)->save();

            return $listing;
        }, attempts: 3);
    }

    /**
     * Rejects an existing fallback record that belongs to another escrow transfer.
     */
    private function assertMatchingStoredItem(
        WebStorageItem $storedItem,
        MarketListing $listing,
        string $accountId,
        string $requestHash,
    ): void {
        $matches = $storedItem->account_id === $accountId
            && hash_equals($storedItem->item_fingerprint, $listing->item_fingerprint)
            && hash_equals($storedItem->item_binary, $listing->item_binary)
            && hash_equals($storedItem->request_hash, $requestHash);

        if (! $matches) {
            throw new MarketListingRejected(MarketListingBlockReason::ItemChanged);
        }
    }

    /**
     * Derives one stable UUID for repeated fallback attempts.
     */
    private function idempotencyKey(MarketListing $listing): string
    {
        return Uuid::uuid5(
            Uuid::NAMESPACE_URL,
            'muonline-market-web-fallback:'.$listing->public_id,
        )->toString();
    }

    /**
     * Binds the fallback record to its listing, recipient and original bytes.
     */
    private function requestHash(MarketListing $listing, string $accountId): string
    {
        $payload = json_encode([
            'listing_id' => $listing->getKey(),
            'account_id' => $accountId,
            'item_fingerprint' => $listing->item_fingerprint,
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $payload);
    }
}
