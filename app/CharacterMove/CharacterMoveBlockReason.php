<?php

namespace App\CharacterMove;

/**
 * Identifies one server-authoritative reason that prevents a character move.
 */
enum CharacterMoveBlockReason: string
{
    case ProfileDisabled = 'profile_disabled';
    case CharacterOnline = 'character_online';
    case InsufficientZen = 'insufficient_zen';
    case AlreadyAtDestination = 'already_at_destination';
    case CharacterNotFound = 'character_not_found';
    case WritesDisabled = 'writes_disabled';
    case OperationInProgress = 'operation_in_progress';
    case IdempotencyConflict = 'idempotency_conflict';
}
