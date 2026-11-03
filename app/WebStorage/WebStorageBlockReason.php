<?php

namespace App\WebStorage;

/**
 * Identifies a safe public reason for rejecting a web storage operation.
 */
enum WebStorageBlockReason: string
{
    case WritesDisabled = 'writes_disabled';
    case CharacterNotFound = 'character_not_found';
    case CharacterOnline = 'character_online';
    case WarehouseNotFound = 'warehouse_not_found';
    case ItemNotFound = 'item_not_found';
    case ItemChanged = 'item_changed';
    case StorageFull = 'storage_full';
    case ItemUnavailable = 'item_unavailable';
    case InsufficientQuantity = 'insufficient_quantity';
    case IdempotencyConflict = 'idempotency_conflict';
}
