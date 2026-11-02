<?php

namespace App\Market;

/**
 * Identifies a safe public reason for rejecting a market escrow operation.
 */
enum MarketListingBlockReason: string
{
    case WritesDisabled = 'writes_disabled';
    case CharacterNotFound = 'character_not_found';
    case CharacterOnline = 'character_online';
    case WarehouseNotFound = 'warehouse_not_found';
    case ItemNotFound = 'item_not_found';
    case ItemChanged = 'item_changed';
    case StorageFull = 'storage_full';
    case ListingUnavailable = 'listing_unavailable';
    case IdempotencyConflict = 'idempotency_conflict';
}
