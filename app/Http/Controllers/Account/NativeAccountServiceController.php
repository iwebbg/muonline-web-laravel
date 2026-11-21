<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Economy\Actions\PurchaseNativeAccountService;
use App\Economy\Exceptions\NativePurchaseRejected;
use App\Economy\NativeCurrency;
use App\Economy\WalletCurrency;
use App\Game\Contracts\AccountMembershipReadRepository;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\GameCurrencyBalanceRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ExchangeGameCurrencyRequest;
use App\Http\Requests\Account\PurchaseGameVipRequest;
use App\Models\AccountWallet;
use App\Models\GameCurrencyExchangeRate;
use App\Models\NativeAccountPurchase;
use App\Models\VipPlan;
use App\Modules\ModuleManager;
use App\Modules\ServerModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Displays private coin/VIP offers and resumes exact interrupted native purchases.
 */
final class NativeAccountServiceController extends Controller
{
    /**
     * Reads account-owned balances and targets without querying or changing another account.
     */
    public function index(
        Request $request, AccountReadRepository $characters, GameCurrencyBalanceRepository $currencies,
        AccountMembershipReadRepository $membership, GameVersionProfile $profile, ModuleManager $modules,
    ): View {
        $account = $request->user();
        abort_unless($account instanceof GameAccount, 403);
        $exchangeEnabled = $modules->enabled(ServerModule::GameCurrencyExchange);
        $vipEnabled = $modules->enabled(ServerModule::Vip) && $profile->capabilities()->accountVip;
        abort_unless($exchangeEnabled || $vipEnabled, 404);

        return view('account.native-services', [
            'exchangeEnabled' => $exchangeEnabled, 'vipEnabled' => $vipEnabled,
            'balances' => $currencies->forAccount($account->username), 'membership' => $membership->forAccount($account->username),
            'characters' => $characters->charactersFor($account->username),
            'walletBalance' => (int) (AccountWallet::query()->where('account_id', $account->username)->where('currency', WalletCurrency::Credits)->value('balance') ?? 0),
            'rates' => GameCurrencyExchangeRate::query()->where('game_profile_key', $profile->key())->where('enabled', true)->orderBy('id')->get(),
            'plans' => VipPlan::query()->available()->whereNotNull('native_account_level')->orderBy('sort_order')->orderBy('id')->get(),
            'purchases' => NativeAccountPurchase::query()->where('account_id', $account->username)->where('game_profile_key', $profile->key())->latest('id')->limit(30)->get(),
            'writesEnabled' => $modules->enabled(ServerModule::Credits) && (bool) config('game.writes_enabled', false) && ! (bool) config('database.connections.game.readonly', true),
        ]);
    }

    /**
     * Purchases only the validated currency and integer pack quantity.
     */
    public function exchange(ExchangeGameCurrencyRequest $request, PurchaseNativeAccountService $service): RedirectResponse
    {
        try {
            $service->exchange($request->user()->getAuthIdentifier(), NativeCurrency::from($request->string('currency')->toString()),
                $request->integer('units'), $request->filled('character_name') ? $request->string('character_name')->toString() : null,
                $request->string('idempotency_key')->toString());
        } catch (NativePurchaseRejected $exception) {
            return back()->withInput()->withErrors(['native_purchase' => __('ui.native_services.errors.'.$exception->reason->value)]);
        }

        return redirect()->route('account.native-services.index')->with('status', __('ui.native_services.completed'));
    }

    /**
     * Purchases the server-selected account VIP tier and duration from one administrator plan.
     */
    public function vip(PurchaseGameVipRequest $request, PurchaseNativeAccountService $service): RedirectResponse
    {
        try {
            $service->purchaseVip($request->user()->getAuthIdentifier(), $request->integer('plan_id'), $request->string('idempotency_key')->toString());
        } catch (NativePurchaseRejected $exception) {
            return back()->withInput()->withErrors(['native_purchase' => __('ui.native_services.errors.'.$exception->reason->value)]);
        }

        return redirect()->route('account.native-services.index')->with('status', __('ui.native_services.completed'));
    }

    /**
     * Resumes an owned pending purchase using its captured original terms and durable key.
     */
    public function resume(Request $request, NativeAccountPurchase $purchase, PurchaseNativeAccountService $service): RedirectResponse
    {
        abort_unless($purchase->account_id === $request->user()->getAuthIdentifier()
            && $purchase->game_profile_key === config('game.profile.key'), 404);
        try {
            $service->resume($request->user()->getAuthIdentifier(), $purchase);
        } catch (NativePurchaseRejected $exception) {
            return back()->withErrors(['native_purchase' => __('ui.native_services.errors.'.$exception->reason->value)]);
        }

        return redirect()->route('account.native-services.index')->with('status', __('ui.native_services.completed'));
    }
}
