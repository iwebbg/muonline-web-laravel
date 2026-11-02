<?php

namespace App\Models;

use Database\Factories\AuctionBidFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records one immutable auction offer and its Credits hold lifecycle.
 */
#[Fillable([
    'market_listing_id',
    'bidder_account_id',
    'amount_credits',
    'idempotency_key',
    'request_hash',
    'hold_wallet_transaction_id',
    'release_wallet_transaction_id',
    'outbid_at',
])]
final class AuctionBid extends Model
{
    /** @use HasFactory<AuctionBidFactory> */
    use HasFactory;

    /**
     * Returns the escrow auction receiving this bid.
     *
     * @return BelongsTo<MarketListing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketListing::class, 'market_listing_id');
    }

    /**
     * Casts monetary and lifecycle values to stable PHP types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_credits' => 'integer',
            'hold_wallet_transaction_id' => 'integer',
            'release_wallet_transaction_id' => 'integer',
            'outbid_at' => 'immutable_datetime',
        ];
    }
}
