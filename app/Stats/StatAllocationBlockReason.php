<?php

namespace App\Stats;

/**
 * Identifies one server-authoritative reason that prevents stat allocation.
 */
enum StatAllocationBlockReason: string
{
    case CharacterOnline = 'character_online';
    case NoPointsAllocated = 'no_points_allocated';
    case InvalidAllocation = 'invalid_allocation';
    case InsufficientPoints = 'insufficient_points';
    case StatLimitExceeded = 'stat_limit_exceeded';
    case CharacterClassUnsupported = 'character_class_unsupported';
    case CharacterNotFound = 'character_not_found';
    case WritesDisabled = 'writes_disabled';
    case OperationInProgress = 'operation_in_progress';
    case IdempotencyConflict = 'idempotency_conflict';
}
