<?php

namespace App\Models;

use App\Market\MarketListingStatus;
use Database\Factories\MarketListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores one original 97d+99i item blob in recoverable market escrow.
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
    'buyer_wallet_transaction_id',
    'seller_wallet_transaction_id',
    'status',
    'sync_attempts',
    'last_sync_attempt_at',
    'last_sync_error',
    'activated_at',
    'purchased_at',
    'delivered_at',
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
     * Casts escrow state and public snapshots to stable PHP values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_vault' => 'integer',
            'source_slot' => 'integer',
            'item_snapshot' => 'array',
            'price_credits' => 'integer',
            'buyer_wallet_transaction_id' => 'integer',
            'seller_wallet_transaction_id' => 'integer',
            'status' => MarketListingStatus::class,
            'sync_attempts' => 'integer',
            'last_sync_attempt_at' => 'immutable_datetime',
            'activated_at' => 'immutable_datetime',
            'purchased_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'returned_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
