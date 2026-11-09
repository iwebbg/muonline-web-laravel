<?php

namespace App\CreditEarning;

/**
 * Identifies a player-safe reason that a Credits earning operation was rejected.
 */
enum CreditEarningBlockReason: string
{
    case ServiceDisabled = 'service_disabled';
    case OfferNotFound = 'offer_not_found';
    case InvalidQuantity = 'invalid_quantity';
    case CharacterNotFound = 'character_not_found';
    case CharacterOnline = 'character_online';
    case ItemsUnavailable = 'items_unavailable';
    case ItemChanged = 'item_changed';
    case DailyLimitReached = 'daily_limit_reached';
    case WritesDisabled = 'writes_disabled';
    case IdempotencyConflict = 'idempotency_conflict';
    case NothingToClaim = 'nothing_to_claim';
}
