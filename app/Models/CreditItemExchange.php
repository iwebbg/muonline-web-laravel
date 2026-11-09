<?php

namespace App\Models;

use App\CreditEarning\CreditExchangeStatus;
use Database\Factories\CreditItemExchangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tracks one recoverable exact-item exchange and its immutable Credits award.
 */
#[Fillable([
    'public_id', 'idempotency_key', 'request_hash', 'account_id', 'character_name',
    'credit_exchange_offer_id', 'quantity', 'credits_awarded', 'offer_snapshot',
    'items_snapshot', 'status', 'wallet_transaction_id', 'failure_code', 'completed_at',
])]
final class CreditItemExchange extends Model
{
    /** @use HasFactory<CreditItemExchangeFactory> */
    use HasFactory;

    /**
     * Casts operation snapshots and status to stable domain types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'credits_awarded' => 'integer',
            'offer_snapshot' => 'array',
            'items_snapshot' => 'array',
            'status' => CreditExchangeStatus::class,
            'wallet_transaction_id' => 'integer',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
