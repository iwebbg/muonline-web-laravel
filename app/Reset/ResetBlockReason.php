<?php

namespace App\Reset;

/**
 * Identifies one server-authoritative reason that prevents a character reset.
 */
enum ResetBlockReason: string
{
    case ProfileDisabled = 'profile_disabled';
    case CharacterOnline = 'character_online';
    case LevelTooLow = 'level_too_low';
    case MaximumResetsReached = 'maximum_resets_reached';
    case InsufficientZen = 'insufficient_zen';
    case WritesDisabled = 'writes_disabled';
    case InventoryPolicyUnsupported = 'inventory_policy_unsupported';
    case CharacterNotFound = 'character_not_found';
    case CharacterClassUnsupported = 'character_class_unsupported';
    case OperationInProgress = 'operation_in_progress';
    case IdempotencyConflict = 'idempotency_conflict';
}
