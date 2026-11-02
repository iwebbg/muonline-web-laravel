<?php

namespace App\Models;

use Database\Factories\VipPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records one immutable character VIP purchase and its wallet debit.
 */
#[Fillable([
    'idempotency_key',
    'request_hash',
    'vip_entitlement_id',
    'vip_plan_id',
    'wallet_transaction_id',
    'account_id',
    'character_name',
    'plan_name',
    'duration_days',
    'price_credits',
    'previous_expires_at',
    'starts_at',
    'expires_at',
])]
final class VipPurchase extends Model
{
    /** @use HasFactory<VipPurchaseFactory> */
    use HasFactory;

    /**
     * Returns the current entitlement extended by this purchase.
     *
     * @return BelongsTo<VipEntitlement, $this>
     */
    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(VipEntitlement::class, 'vip_entitlement_id');
    }

    /**
     * Returns the immutable wallet debit paying for this purchase.
     *
     * @return BelongsTo<WalletTransaction, $this>
     */
    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    /**
     * Casts purchase snapshots without losing date or integer precision.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'price_credits' => 'integer',
            'previous_expires_at' => 'immutable_datetime',
            'starts_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
