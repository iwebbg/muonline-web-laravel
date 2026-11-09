<?php

namespace App\Http\Controllers\Admin;

use App\CreditEarning\CreditExchangeOfferPresenter;
use App\Game\Contracts\ItemCatalog;
use App\Game\Contracts\ItemGrantRules;
use App\Http\AccountPanelConfiguration;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateEconomySettingRequest;
use App\Models\Administrator;
use App\Models\CreditExchangeOffer;
use App\Shop\Actions\UpdateEconomySettings;
use App\Shop\EconomySettingReader;
use App\Shop\EconomySettingRevisionPresenter;
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
    public function edit(
        EconomySettingReader $settingReader,
        EconomySettingRevisionPresenter $revisionPresenter,
        ItemCatalog $itemCatalog,
        ItemGrantRules $itemGrantRules,
        CreditExchangeOfferPresenter $offerPresenter,
        AccountPanelConfiguration $accountPanelConfiguration,
    ): View {
        $itemDefinitions = array_map(static fn ($definition): array => [
            'code' => $definition->code(),
            'name' => $definition->name,
            'image' => $definition->image,
            'excellentType' => $definition->excellentType,
            'optionType' => $definition->optionType,
        ], $itemCatalog->allDefinitions());
        $exchangeOffers = CreditExchangeOffer::query()
            ->where('game_profile_key', (string) config('game.profile.key'))
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.economy-settings', [
            'settings' => $settingReader->current()->toClientArray(),
            'revision' => $settingReader->revision(),
            'recentRevisions' => $revisionPresenter->present($settingReader->recentRevisions()),
            'itemDefinitions' => array_column($itemDefinitions, null, 'code'),
            'itemLevels' => $itemGrantRules->itemLevels(),
            'optionLevels' => $itemGrantRules->optionLevels(),
            'itemOptionLabels' => $accountPanelConfiguration->itemOptionLabels(),
            'exchangeOffers' => $exchangeOffers->map(static fn (CreditExchangeOffer $offer): array => [
                'model' => $offer,
                'item' => $offerPresenter->present($offer),
            ]),
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
