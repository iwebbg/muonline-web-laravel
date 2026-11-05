<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Audit\AuditLogReader;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterAuditLogRequest;
use Illuminate\View\View;

/**
 * Displays the immutable application audit trail to authenticated administrators.
 */
final class AuditLogController extends Controller
{
    /**
     * Displays recent audit events using allow-listed filters only.
     */
    public function __invoke(
        FilterAuditLogRequest $request,
        AuditLogReader $auditLogReader,
    ): View {
        $supportedTypes = $auditLogReader->supportedTypes();
        $supportedStatuses = [
            'pending',
            'completed',
            'failed',
        ];
        $type = $request->type();
        $status = $request->status();
        $search = $request->search();
        $dateFrom = $request->dateFrom();
        $dateTo = $request->dateTo();
        $dateFromInput = $dateFrom->format('Y-m-d');
        $dateToInput = $dateTo->format('Y-m-d');
        $auditEntries = $auditLogReader
            ->paginate(
                type: $type,
                status: $status,
                search: $search,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
            )
            ->withQueryString()
            ->appends([
                'date_from' => $dateFromInput,
                'date_to' => $dateToInput,
            ]);

        return view('admin.audit-log', [
            'auditEntries' => $auditEntries,
            'selectedType' => $type,
            'selectedStatus' => $status,
            'search' => $search,
            'dateFrom' => $dateFromInput,
            'dateTo' => $dateToInput,
            'supportedTypes' => $supportedTypes,
            'supportedStatuses' => $supportedStatuses,
        ]);
    }
}
