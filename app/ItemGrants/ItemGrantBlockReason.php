<?php

namespace App\ItemGrants;

/**
 * Describes stable reasons that prevent an administrator item grant.
 */
enum ItemGrantBlockReason: string
{
    case WritesDisabled = 'writes_disabled';
    case PermissionDenied = 'permission_denied';
    case UnknownItem = 'unknown_item';
    case AccountOnline = 'account_online';
    case WarehouseNotFound = 'warehouse_not_found';
    case WarehouseFull = 'warehouse_full';
    case IdempotencyConflict = 'idempotency_conflict';
    case OperationInProgress = 'operation_in_progress';
    case PreviousFailure = 'previous_failure';
}
