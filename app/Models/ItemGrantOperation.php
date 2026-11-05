<?php

namespace App\Models;

use App\Database\Casts\SqlServerBinary;
use App\Game\Operations\CharacterOperationStatus;
use Database\Factories\ItemGrantOperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Records one idempotent and fully audited administrator warehouse item grant.
 */
#[Fillable([
    'idempotency_key',
    'request_hash',
    'administrator_id',
    'account_id',
    'item_code',
    'item_name',
    'serial',
    'item_binary',
    'status',
    'reason',
    'context_snapshot',
    'before_snapshot',
    'after_snapshot',
    'failure_code',
    'completed_at',
])]
#[Hidden(['idempotency_key', 'request_hash', 'item_binary'])]
final class ItemGrantOperation extends Model
{
    /** @use HasFactory<ItemGrantOperationFactory> */
    use HasFactory;

    /**
     * Casts audit state while retaining the exact item bytes for safe retries.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'serial' => 'integer',
            'item_binary' => SqlServerBinary::class,
            'status' => CharacterOperationStatus::class,
            'context_snapshot' => 'array',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
