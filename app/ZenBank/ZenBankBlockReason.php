<?php

namespace App\ZenBank;

/**
 * Identifies safe player-facing Zen Bank rejection reasons.
 */
enum ZenBankBlockReason: string
{
    case WritesDisabled = 'writes_disabled';
    case CharacterNotFound = 'character_not_found';
    case CharacterOnline = 'character_online';
    case WarehouseNotFound = 'warehouse_not_found';
    case InvalidAmount = 'invalid_amount';
    case InsufficientGameZen = 'insufficient_game_zen';
    case InsufficientBankZen = 'insufficient_bank_zen';
    case PendingOperation = 'pending_operation';
    case IdempotencyConflict = 'idempotency_conflict';
}
