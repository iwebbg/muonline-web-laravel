<?php

namespace App\Http\Controllers\Admin;

use App\Economy\GameCurrencyExchangeRateRevisionPresenter;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateGameCurrencyExchangeRateRequest;
use App\Models\GameCurrencyExchangeRate;
use App\Models\GameCurrencyExchangeRateRevision;
use App\Models\NativeAccountPurchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Manages profile-scoped coin exchange rates and their immutable revision history.
 */
final class GameCurrencyExchangeRateController extends Controller
{
    /**
     * Shows native exchange policy and recent purchases without modifying game balances.
     */
    public function index(GameVersionProfile $profile, GameCurrencyExchangeRateRevisionPresenter $revisionPresenter): View
    {
        $revisions = GameCurrencyExchangeRateRevision::query()
            ->whereIn('game_currency_exchange_rate_id', GameCurrencyExchangeRate::query()
                ->where('game_profile_key', $profile->key())->select('id'))
            ->latest('id')->limit(25)->get();

        return view('admin.game-currency-rates', [
            'rates' => GameCurrencyExchangeRate::query()->where('game_profile_key', $profile->key())->orderBy('id')->get(),
            'recentRevisions' => $revisionPresenter->present($revisions),
            'purchases' => NativeAccountPurchase::query()->where('game_profile_key', $profile->key())->latest('id')->limit(25)->get(),
        ]);
    }

    /**
     * Locks the selected policy, rejects stale edits and captures its exact before/after revision.
     */
    public function update(UpdateGameCurrencyExchangeRateRequest $request, GameCurrencyExchangeRate $rate, GameVersionProfile $profile): RedirectResponse
    {
        abort_unless($rate->game_profile_key === $profile->key(), 404);
        DB::transaction(function () use ($request, $rate): void {
            $locked = GameCurrencyExchangeRate::query()->lockForUpdate()->findOrFail($rate->getKey());
            if ($locked->revision !== $request->integer('revision')) {
                throw ValidationException::withMessages(['revision' => __('ui.native_services.stale_revision')]);
            }
            $before = $locked->snapshot();
            $locked->update([
                ...$request->safe()->except(['revision', 'enabled']),
                'enabled' => $request->boolean('enabled'), 'revision' => $locked->revision + 1,
            ]);
            GameCurrencyExchangeRateRevision::query()->create([
                'game_currency_exchange_rate_id' => $locked->getKey(), 'administrator_id' => $request->user('admin')->getAuthIdentifier(),
                'revision' => $locked->revision, 'before_snapshot' => $before, 'snapshot' => $locked->snapshot(), 'created_at' => now(),
            ]);
        });

        return back()->with('status', __('ui.native_services.rate_saved'));
    }
}
