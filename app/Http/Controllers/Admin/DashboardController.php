<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Game\Contracts\RealmReadRepository;
use App\Http\Controllers\Controller;
use App\Http\SystemReadiness;
use App\Models\Administrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Displays the administrator overview with service and realm analytics status.
 */
final class DashboardController extends Controller
{
    /**
     * Displays service readiness and the current read-only realm snapshot.
     */
    public function __invoke(
        Request $request,
        SystemReadiness $systemReadiness,
        RealmReadRepository $realmReadRepository,
    ): View|RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        if (! $administrator->hasPermission(AdministratorPermission::ManageSettings)) {
            if (
                $administrator->hasPermission(AdministratorPermission::ModerateAccounts)
                || $administrator->hasPermission(AdministratorPermission::ModerateCharacters)
            ) {
                return redirect()->route('admin.moderation');
            }

            if ($administrator->hasPermission(AdministratorPermission::ManageContent)) {
                return redirect()->route('admin.news-posts.index');
            }

            if ($administrator->hasPermission(AdministratorPermission::ViewAuditLog)) {
                return redirect()->route('admin.audit-log');
            }

            abort(403);
        }

        $readiness = $systemReadiness->inspect();
        $realmAnalytics = $readiness['checks']['gameDatabase']
            ? $realmReadRepository->analytics()->toArray()
            : null;

        return view('admin.dashboard', [
            'realmAnalytics' => $realmAnalytics,
            'systemReady' => $readiness['ready'],
        ]);
    }
}
