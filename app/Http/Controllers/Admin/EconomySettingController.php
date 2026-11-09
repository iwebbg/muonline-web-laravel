<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateEconomySettingRequest;
use App\Models\Administrator;
use App\Shop\Actions\UpdateEconomySettings;
use App\Shop\EconomySettingReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Displays and updates version-independent paid-service settings.
 */
final class EconomySettingController extends Controller
{
    /**
     * Displays the current generator markup and character service pricing.
     */
    public function edit(EconomySettingReader $settingReader): View
    {
        return view('admin.economy-settings', [
            'settings' => $settingReader->current()->toClientArray(),
            'revision' => $settingReader->revision(),
        ]);
    }

    /**
     * Saves one audited paid-service settings revision.
     */
    public function update(
        UpdateEconomySettingRequest $request,
        UpdateEconomySettings $updateEconomySettings,
    ): RedirectResponse {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        $updateEconomySettings->handle(
            settings: $request->settings(),
            administrator: $administrator,
        );

        return redirect()
            ->route('admin.economy-settings.edit')
            ->with('status', __('ui.admin.economy_settings_saved'));
    }
}
