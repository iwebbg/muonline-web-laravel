<?php

namespace App\Models;

use App\Game\Operations\CharacterOperationStatus;
use Database\Factories\CharacterMoveOperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Records idempotency and exact location snapshots for one character move.
 */
#[Fillable([
    'idempotency_key',
    'account_id',
    'character_name',
    'status',
    'rule_snapshot',
    'before_snapshot',
    'after_snapshot',
    'failure_code',
    'completed_at',
])]
final class CharacterMoveOperation extends Model
{
    /** @use HasFactory<CharacterMoveOperationFactory> */
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
            'rule_snapshot' => 'array',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
