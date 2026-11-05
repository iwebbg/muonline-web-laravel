<?php

namespace App\Shop;

/**
 * Identifies a safe player-facing reason why a shop purchase was rejected.
 */
enum ShopPurchaseBlockReason: string
{
    case OfferUnavailable = 'offer_unavailable';
    case InsufficientCredits = 'insufficient_credits';
    case IdempotencyConflict = 'idempotency_conflict';
}
