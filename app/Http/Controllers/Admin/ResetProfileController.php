<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateResetProfileRequest;
use App\Models\Administrator;
use App\Reset\Actions\UpdateResetProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Persists validated reset settings without writing to the MuOnline database.
 */
final class ResetProfileController extends Controller
{
    /**
     * Stores a new audited reset policy revision for the authenticated administrator.
     */
    public function update(
        UpdateResetProfileRequest $request,
        UpdateResetProfile $updateResetProfile,
    ): JsonResponse|RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        $profile = $updateResetProfile->handle(
            settings: $request->settings(),
            administrator: $administrator,
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('ui.admin.draft_saved'),
                'revision' => $profile->revision,
                'settings' => $request->settings()->toClientArray(),
            ]);
        }

        return back()->with('status', __('ui.admin.draft_saved'));
    }
}
