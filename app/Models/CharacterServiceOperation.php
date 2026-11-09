<?php

namespace App\Models;

use App\CharacterServices\CharacterServiceType;
use App\Game\Operations\CharacterOperationStatus;
use Database\Factories\CharacterServiceOperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audits one paid, idempotent character rename or class change.
 */
#[Fillable([
    'idempotency_key',
    'request_hash',
    'account_id',
    'service_type',
    'character_name',
    'requested_name',
    'target_class_code',
    'requested_amount',
    'status',
    'price_credits',
    'wallet_transaction_id',
    'refund_wallet_transaction_id',
    'settings_snapshot',
    'before_snapshot',
    'after_snapshot',
    'failure_code',
    'completed_at',
])]
final class CharacterServiceOperation extends Model
{
    /** @use HasFactory<CharacterServiceOperationFactory> */
    use HasFactory;

    /**
     * Returns the immutable Credits debit for this operation.
     *
     * @return BelongsTo<WalletTransaction, $this>
     */
    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    /**
     * Returns the compensating refund when the game write was rejected.
     *
     * @return BelongsTo<WalletTransaction, $this>
     */
    public function refundWalletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'refund_wallet_transaction_id');
    }

    /**
     * Casts operation state, service kind and audit snapshots.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service_type' => CharacterServiceType::class,
            'status' => CharacterOperationStatus::class,
            'target_class_code' => 'integer',
            'requested_amount' => 'integer',
            'price_credits' => 'integer',
            'settings_snapshot' => 'array',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
