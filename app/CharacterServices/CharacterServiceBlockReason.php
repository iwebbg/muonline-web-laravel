<?php

namespace App\CharacterServices;

/**
 * Identifies a stable public reason why a character service was rejected.
 */
enum CharacterServiceBlockReason: string
{
    case WritesDisabled = 'writes_disabled';
    case ServiceDisabled = 'service_disabled';
    case CharacterNotFound = 'character_not_found';
    case CharacterOnline = 'character_online';
    case NameTaken = 'name_taken';
    case SameName = 'same_name';
    case InvalidClass = 'invalid_class';
    case SameClassFamily = 'same_class_family';
    case InsufficientCredits = 'insufficient_credits';
    case IdempotencyConflict = 'idempotency_conflict';
    case OperationInProgress = 'operation_in_progress';
}
