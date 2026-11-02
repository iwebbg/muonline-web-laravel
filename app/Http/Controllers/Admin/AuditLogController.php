<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Audit\AuditLogReader;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Displays the immutable application audit trail to authenticated administrators.
 */
final class AuditLogController extends Controller
{
    /**
     * Displays recent audit events using allow-listed filters only.
     */
    public function __invoke(Request $request, AuditLogReader $auditLogReader): View
    {
        $supportedTypes = $auditLogReader->supportedTypes();
        $supportedStatuses = [
            'pending',
            'completed',
            'failed',
        ];
        $requestedType = $request->string('type')->toString();
        $requestedStatus = $request->string('status')->toString();
        $search = $request->string('search')->trim()->limit(80)->toString();
        $type = in_array($requestedType, $supportedTypes, true)
            ? $requestedType
            : null;
        $status = in_array($requestedStatus, $supportedStatuses, true)
            ? $requestedStatus
            : null;

        return view('admin.audit-log', [
            'auditEntries' => $auditLogReader->latest(
                type: $type,
                status: $status,
                search: $search,
            ),
            'selectedType' => $type,
            'selectedStatus' => $status,
            'search' => $search,
            'supportedTypes' => $supportedTypes,
            'supportedStatuses' => $supportedStatuses,
        ]);
    }
}
