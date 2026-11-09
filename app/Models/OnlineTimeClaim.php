<?php

namespace App\Models;

use Database\Factories\OnlineTimeClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Records one idempotent transfer of online-time Credits into the wallet.
 */
#[Fillable([
    'public_id', 'idempotency_key', 'request_hash', 'account_id', 'credits_claimed',
    'wallet_transaction_id', 'completed_at',
])]
final class OnlineTimeClaim extends Model
{
    /** @use HasFactory<OnlineTimeClaimFactory> */
    use HasFactory;

    /**
     * Casts immutable claim values to exact application types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credits_claimed' => 'integer',
            'wallet_transaction_id' => 'integer',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
