<?php

namespace App\Models;

use App\Database\Casts\LowercaseUuid;
use App\Database\Casts\SqlServerBinary;
use App\Market\MarketListingStatus;
use App\Market\MarketSaleType;
use App\Market\MarketStorageDestination;
use Database\Factories\MarketListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stores one adapter-owned item blob in recoverable market escrow.
 */
#[Fillable([
    'public_id',
    'idempotency_key',
    'purchase_idempotency_key',
    'request_hash',
    'purchase_request_hash',
    'seller_account_id',
    'buyer_account_id',
    'source_character_name',
    'source_vault',
    'source_slot',
    'item_binary',
    'item_fingerprint',
    'escrow_key',
    'item_snapshot',
    'price_credits',
    'sale_type',
    'minimum_bid_credits',
    'bid_increment_credits',
    'highest_bid_credits',
    'highest_bidder_account_id',
    'highest_bid_id',
    'buyer_wallet_transaction_id',
    'seller_wallet_transaction_id',
    'status',
    'storage_destination',
    'sync_attempts',
    'last_sync_attempt_at',
    'last_sync_error',
    'activated_at',
    'purchased_at',
    'delivered_at',
    'settled_at',
    'returned_at',
    'expires_at',
])]
final class MarketListing extends Model
{
    /** @use HasFactory<MarketListingFactory> */
    use HasFactory;

    /**
     * Returns the stable public identifier used in player-facing URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Returns all immutable bids placed against this escrow listing.
     *
     * @return HasMany<AuctionBid, $this>
     */
    public function bids(): HasMany
    {
        return $this->hasMany(AuctionBid::class);
    }

    /**
     * Casts escrow state and public snapshots to stable PHP values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'public_id' => LowercaseUuid::class,
            'source_vault' => 'integer',
            'source_slot' => 'integer',
            'item_binary' => SqlServerBinary::class,
            'item_snapshot' => 'array',
            'price_credits' => 'integer',
            'sale_type' => MarketSaleType::class,
            'minimum_bid_credits' => 'integer',
            'bid_increment_credits' => 'integer',
            'highest_bid_credits' => 'integer',
            'highest_bid_id' => 'integer',
            'buyer_wallet_transaction_id' => 'integer',
            'seller_wallet_transaction_id' => 'integer',
            'status' => MarketListingStatus::class,
            'storage_destination' => MarketStorageDestination::class,
            'sync_attempts' => 'integer',
            'last_sync_attempt_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'purchased_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'settled_at' => 'immutable_datetime',
            'returned_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
