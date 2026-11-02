<?php

namespace App\Models;

use Database\Factories\MarketAdminActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records one immutable administrator recovery action against market escrow.
 */
#[Fillable([
    'market_listing_id',
    'administrator_id',
    'action',
    'before_status',
    'after_status',
    'outcome',
    'failure_code',
    'context_snapshot',
])]
final class MarketAdminAction extends Model
{
    /** @use HasFactory<MarketAdminActionFactory> */
    use HasFactory;

    /**
     * Returns the escrow listing affected by this action.
     *
     * @return BelongsTo<MarketListing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketListing::class, 'market_listing_id');
    }

    /**
     * Returns the administrator who initiated this action when still present.
     *
     * @return BelongsTo<Administrator, $this>
     */
    public function administrator(): BelongsTo
    {
        return $this->belongsTo(Administrator::class);
    }

    /**
     * Casts the immutable action context to a safe associative array.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context_snapshot' => 'array',
        ];
    }
}
