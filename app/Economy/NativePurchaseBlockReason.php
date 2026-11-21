<?php

namespace App\Economy;

/**
 * Carries safe player-facing failures across native account purchase boundaries.
 */
enum NativePurchaseBlockReason: string
{
    case WritesDisabled = 'writes_disabled';
    case ModuleDisabled = 'module_disabled';
    case OfferUnavailable = 'offer_unavailable';
    case InvalidQuantity = 'invalid_quantity';
    case CharacterNotFound = 'character_not_found';
    case AccountNotFound = 'account_not_found';
    case AccountOnline = 'account_online';
    case BalanceLimit = 'balance_limit';
    case InsufficientCredits = 'insufficient_credits';
    case IdempotencyConflict = 'idempotency_conflict';
    case PurchasePending = 'purchase_pending';
    case DifferentVipTier = 'different_vip_tier';
}
