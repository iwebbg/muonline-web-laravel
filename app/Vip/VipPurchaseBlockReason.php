<?php

namespace App\Vip;

/**
 * Identifies a safe public reason for rejecting a character VIP purchase.
 */
enum VipPurchaseBlockReason: string
{
    case PlanUnavailable = 'plan_unavailable';
    case CharacterNotFound = 'character_not_found';
    case CharacterOnline = 'character_online';
    case InsufficientCredits = 'insufficient_credits';
    case WritesDisabled = 'writes_disabled';
    case IdempotencyConflict = 'idempotency_conflict';
}
