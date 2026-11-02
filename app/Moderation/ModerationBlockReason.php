<?php

namespace App\Moderation;

/**
 * Describes stable rejection reasons returned by the moderation write boundary.
 */
enum ModerationBlockReason: string
{
    case WritesDisabled = 'writes_disabled';
    case PermissionDenied = 'permission_denied';
    case TargetNotFound = 'target_not_found';
    case TargetOnline = 'target_online';
    case UnsupportedControlCode = 'unsupported_control_code';
    case IdempotencyConflict = 'idempotency_conflict';
    case OperationInProgress = 'operation_in_progress';
}
