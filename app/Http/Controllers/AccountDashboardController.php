<?php

namespace App\Http\Controllers;

use App\Auth\GameAccount;
use App\Economy\WalletCurrency;
use App\Game\Contracts\AccountReadRepository;
use App\Models\AccountWallet;
use App\Models\VipEntitlement;
use App\Models\VipPlan;
use App\Models\VipPurchase;
use App\Modules\ModuleManager;
use App\Modules\ServerModule;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Displays private character data for the authenticated MuOnline account.
 */
final class AccountDashboardController extends Controller
{
    /**
     * Creates the controller with the version-aware private account reader.
     */
    public function __construct(
        private readonly AccountReadRepository $accountReadRepository,
        private readonly ModuleManager $moduleManager,
    ) {}

    /**
     * Displays only characters owned by the current authenticated account.
     */
    public function __invoke(Request $request): View
    {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $characterEntries = $this->accountReadRepository->charactersFor($account->username);
        $characters = [];

        foreach ($characterEntries as $characterEntry) {
            $characters[] = $characterEntry->toArray();
        }

        $wallet = AccountWallet::query()
            ->where('account_id', $account->username)
            ->where('currency', WalletCurrency::Credits)
            ->first();
        $walletTransactions = $wallet === null
            ? collect()
            : $wallet->transactions()
                ->latest('id')
                ->limit(20)
                ->get();
        $vipEnabled = $this->moduleManager->enabled(ServerModule::Vip);
        $vipPlanEntries = $vipEnabled
            ? VipPlan::query()
                ->available()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : collect();
        $vipPlans = [];

        foreach ($vipPlanEntries as $vipPlanEntry) {
            $vipPlans[] = [
                'id' => $vipPlanEntry->getKey(),
                'name' => $vipPlanEntry->localizedName(app()->getLocale()),
                'description' => $vipPlanEntry->localizedDescription(app()->getLocale()),
                'durationDays' => $vipPlanEntry->duration_days,
                'priceCredits' => $vipPlanEntry->price_credits,
            ];
        }

        $vipEntitlements = $vipEnabled
            ? VipEntitlement::query()
                ->where('account_id', $account->username)
                ->get()
                ->keyBy('character_name')
            : collect();
        $activeVipEntitlementCount = 0;

        foreach ($vipEntitlements as $vipEntitlement) {
            if ($vipEntitlement->expires_at->isFuture()) {
                $activeVipEntitlementCount++;
            }
        }

        $vipPurchases = $vipEnabled
            ? VipPurchase::query()
                ->where('account_id', $account->username)
                ->latest('id')
                ->limit(10)
                ->get()
            : collect();

        return view('account.dashboard', [
            'account' => $account,
            'characters' => $characters,
            'walletBalance' => $wallet?->balance ?? 0,
            'walletTransactions' => $walletTransactions,
            'vipPlans' => $vipPlans,
            'vipEntitlements' => $vipEntitlements,
            'activeVipEntitlementCount' => $activeVipEntitlementCount,
            'vipPurchases' => $vipPurchases,
            'vipIdempotencyKey' => (string) Str::uuid(),
            'gameWritesEnabled' => (bool) config('game.writes_enabled', false)
                && ! (bool) config('database.connections.game.readonly', true),
        ]);
    }
}
