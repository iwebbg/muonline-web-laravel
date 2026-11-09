<?php

namespace App\Models;

use App\Game\Operations\CharacterOperationStatus;
use Database\Factories\CharacterResetOperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Records the durable idempotency and audit state of one character reset request.
 */
#[Fillable([
    'idempotency_key',
    'account_id',
    'character_name',
    'status',
    'settings_snapshot',
    'before_snapshot',
    'after_snapshot',
    'failure_code',
    'reward_wallet_transaction_id',
    'account_reward_id',
    'reward_failure_code',
    'completed_at',
])]
final class CharacterResetOperation extends Model
{
    /** @use HasFactory<CharacterResetOperationFactory> */
    use HasFactory;

    /**
     * Casts operation state and audit payloads to typed application values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CharacterOperationStatus::class,
            'settings_snapshot' => 'array',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'completed_at' => 'immutable_datetime',
            'reward_wallet_transaction_id' => 'integer',
            'account_reward_id' => 'integer',
        ];
    }
}
