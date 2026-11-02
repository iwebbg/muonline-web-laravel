<?php

namespace App\Models;

use App\WebStorage\WebStorageItemStatus;
use Database\Factories\WebStorageItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores one original 97d+99i item blob in a player's recoverable web vault.
 */
#[Fillable([
    'public_id',
    'idempotency_key',
    'request_hash',
    'account_id',
    'source_character_name',
    'source_vault',
    'source_slot',
    'item_binary',
    'item_fingerprint',
    'escrow_key',
    'item_snapshot',
    'status',
    'sync_attempts',
    'last_sync_attempt_at',
    'last_sync_error',
    'stored_at',
    'withdrawn_at',
])]
final class WebStorageItem extends Model
{
    /** @use HasFactory<WebStorageItemFactory> */
    use HasFactory;

    /**
     * Returns the stable public identifier used in player routes.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Casts storage lifecycle and snapshots to stable PHP values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source_vault' => 'integer',
            'source_slot' => 'integer',
            'item_snapshot' => 'array',
            'status' => WebStorageItemStatus::class,
            'sync_attempts' => 'integer',
            'last_sync_attempt_at' => 'immutable_datetime',
            'stored_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }
}
