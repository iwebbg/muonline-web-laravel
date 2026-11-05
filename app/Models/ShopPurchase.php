<?php

namespace App\Models;

use Database\Factories\ShopPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Records one immutable Credits purchase and its offer snapshot.
 */
#[Fillable(['public_id', 'idempotency_key', 'request_hash', 'account_id', 'shop_offer_id', 'offer_snapshot', 'price_credits', 'wallet_transaction_id'])]
final class ShopPurchase extends Model
{
    /** @use HasFactory<ShopPurchaseFactory> */
    use HasFactory;

    /**
     * Uses the stable public purchase identifier in external references.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Returns the current offer record when it still exists.
     *
     * @return BelongsTo<ShopOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(ShopOffer::class, 'shop_offer_id');
    }

    /**
     * Returns the reward ownership created by this purchase.
     *
     * @return HasOne<AccountReward, $this>
     */
    public function reward(): HasOne
    {
        return $this->hasOne(AccountReward::class, 'shop_purchase_id');
    }

    /**
     * Casts the commercial snapshot and integer price without losing precision.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['offer_snapshot' => 'array', 'price_credits' => 'integer'];
    }
}
