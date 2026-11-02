<?php

namespace App\PkClear;

/**
 * Identifies one server-authoritative reason that prevents clearing PK state.
 */
enum PkClearBlockReason: string
{
    case ProfileDisabled = 'profile_disabled';
    case CharacterOnline = 'character_online';
    case LevelTooLow = 'level_too_low';
    case CharacterNotKiller = 'character_not_killer';
    case PriceLimitExceeded = 'price_limit_exceeded';
    case InsufficientZen = 'insufficient_zen';
    case CharacterNotFound = 'character_not_found';
    case WritesDisabled = 'writes_disabled';
    case OperationInProgress = 'operation_in_progress';
    case IdempotencyConflict = 'idempotency_conflict';
}
