<?php

namespace App\Models;

use App\Database\Casts\LowercaseUuid;
use App\Database\Casts\SqlServerBinary;
use App\WebStorage\ItemStorageLocation;
use App\WebStorage\WebStorageContext;
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
    'storage_context',
    'source_character_name',
    'source_location',
    'source_vault',
    'source_slot',
    'withdrawal_location',
    'withdrawal_character_name',
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
            'public_id' => LowercaseUuid::class,
            'source_vault' => 'integer',
            'source_slot' => 'integer',
            'item_binary' => SqlServerBinary::class,
            'storage_context' => WebStorageContext::class,
            'source_location' => ItemStorageLocation::class,
            'withdrawal_location' => ItemStorageLocation::class,
            'item_snapshot' => 'array',
            'status' => WebStorageItemStatus::class,
            'sync_attempts' => 'integer',
            'last_sync_attempt_at' => 'immutable_datetime',
            'stored_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }
}
