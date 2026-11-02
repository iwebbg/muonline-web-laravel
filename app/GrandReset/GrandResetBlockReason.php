<?php

namespace App\GrandReset;

/**
 * Identifies one server-authoritative reason that prevents a grand reset.
 */
enum GrandResetBlockReason: string
{
    case ProfileDisabled = 'profile_disabled';
    case CharacterOnline = 'character_online';
    case LevelTooLow = 'level_too_low';
    case InsufficientResets = 'insufficient_resets';
    case MaximumGrandResetsReached = 'maximum_grand_resets_reached';
    case InsufficientZen = 'insufficient_zen';
    case PointLimitExceeded = 'point_limit_exceeded';
    case InventoryPolicyUnsupported = 'inventory_policy_unsupported';
    case CharacterClassUnsupported = 'character_class_unsupported';
    case CharacterNotFound = 'character_not_found';
    case WritesDisabled = 'writes_disabled';
    case OperationInProgress = 'operation_in_progress';
    case IdempotencyConflict = 'idempotency_conflict';
}
