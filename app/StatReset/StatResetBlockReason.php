<?php

namespace App\StatReset;

/**
 * Identifies one server-authoritative reason that prevents a stat reset.
 */
enum StatResetBlockReason: string
{
    case ProfileDisabled = 'profile_disabled';
    case CharacterOnline = 'character_online';
    case LevelTooLow = 'level_too_low';
    case InsufficientResets = 'insufficient_resets';
    case InsufficientZen = 'insufficient_zen';
    case NoAllocatedStats = 'no_allocated_stats';
    case CharacterStatsInvalid = 'character_stats_invalid';
    case PointLimitExceeded = 'point_limit_exceeded';
    case CharacterClassUnsupported = 'character_class_unsupported';
    case CharacterNotFound = 'character_not_found';
    case WritesDisabled = 'writes_disabled';
    case OperationInProgress = 'operation_in_progress';
    case IdempotencyConflict = 'idempotency_conflict';
}
