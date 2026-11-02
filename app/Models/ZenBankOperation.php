<?php

namespace App\Models;

use App\ZenBank\ZenBankOperationStatus;
use App\ZenBank\ZenBankOperationType;
use Database\Factories\ZenBankOperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records one replay-safe transfer between game warehouse and web Zen Bank.
 */
#[Fillable([
    'public_id',
    'idempotency_key',
    'request_hash',
    'account_id',
    'character_name',
    'type',
    'amount',
    'before_game_zen',
    'after_game_zen',
    'before_bank_balance',
    'after_bank_balance',
    'status',
    'sync_attempts',
    'last_sync_attempt_at',
    'last_sync_error',
    'completed_at',
    'account_zen_balance_id',
])]
final class ZenBankOperation extends Model
{
    /** @use HasFactory<ZenBankOperationFactory> */
    use HasFactory;

    /**
     * Returns the account balance reserved or changed by this transfer.
     *
     * @return BelongsTo<AccountZenBalance, $this>
     */
    public function balance(): BelongsTo
    {
        return $this->belongsTo(AccountZenBalance::class, 'account_zen_balance_id');
    }

    /**
     * Returns the stable public identifier used in player routes.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Casts operation state, amounts and timestamps to stable PHP values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ZenBankOperationType::class,
            'amount' => 'integer',
            'before_game_zen' => 'integer',
            'after_game_zen' => 'integer',
            'before_bank_balance' => 'integer',
            'after_bank_balance' => 'integer',
            'status' => ZenBankOperationStatus::class,
            'sync_attempts' => 'integer',
            'last_sync_attempt_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
