<?php

namespace App\Http\Controllers;

use App\Http\SystemReadiness;
use Illuminate\Http\JsonResponse;

/**
 * Exposes a non-sensitive readiness signal for deployment monitoring.
 */
final class ReadinessController extends Controller
{
    /**
     * Returns success only when every required runtime dependency is available.
     */
    public function __invoke(SystemReadiness $systemReadiness): JsonResponse
    {
        $report = $systemReadiness->inspect();
        $statusCode = $report['ready'] ? 200 : 503;
        $status = $report['ready'] ? 'ok' : 'unavailable';

        return response()->json([
            'status' => $status,
        ], $statusCode);
    }
}
