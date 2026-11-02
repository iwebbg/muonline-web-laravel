<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\Modules\ModuleProfileRevisionConflict;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateModuleProfileRequest;
use App\Models\Administrator;
use App\Models\ModuleProfile;
use App\Models\ModuleProfileRevision;
use App\Modules\Actions\UpdateModuleProfile;
use App\Modules\ModuleManager;
use App\Modules\ServerModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Manages player-facing module availability for the active server profile.
 */
final class ModuleProfileController extends Controller
{
    /**
     * Displays current switches and recent immutable revisions.
     */
    public function index(ModuleManager $moduleManager): View
    {
        $moduleCards = [];

        foreach (ServerModule::cases() as $module) {
            $moduleCards[] = [
                'key' => $module->value,
                'enabled' => $moduleManager->enabled($module),
            ];
        }

        $recentRevisions = ModuleProfileRevision::query()
            ->whereIn(
                'module_profile_id',
                ModuleProfile::query()
                    ->select('id')
                    ->where('profile_key', $moduleManager->profileKey())
                    ->where('game_version', $moduleManager->gameVersion()),
            )
            ->latest('id')
            ->limit(15)
            ->get();

        return view('admin.modules', [
            'moduleCards' => $moduleCards,
            'profileKey' => $moduleManager->profileKey(),
            'gameVersion' => $moduleManager->gameVersion(),
            'revision' => $moduleManager->revision(),
            'recentRevisions' => $recentRevisions,
        ]);
    }

    /**
     * Persists one atomic module snapshot or rejects a stale administrator form.
     */
    public function update(
        UpdateModuleProfileRequest $request,
        UpdateModuleProfile $updateModuleProfile,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        try {
            $updateModuleProfile->execute(
                settings: $request->settings(),
                expectedRevision: $request->expectedRevision(),
                administrator: $administrator,
            );
        } catch (ModuleProfileRevisionConflict) {
            return back()->withErrors([
                'modules' => __('ui.admin.modules_revision_conflict'),
            ]);
        }

        return back()->with('status', __('ui.admin.modules_updated'));
    }
}
