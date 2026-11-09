<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\CreditEarning\CreditExchangeOfferPresenter;
use App\CreditEarning\CreditExchangeStatus;
use App\CreditEarning\ItemExchangeMatcher;
use App\Economy\WalletCurrency;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\ItemStorageRepository;
use App\Http\Controllers\Controller;
use App\Models\AccountWallet;
use App\Models\CreditExchangeOffer;
use App\Models\CreditItemExchange;
use App\Models\CreditPackage;
use App\Models\CreditPayment;
use App\Models\OnlineTimeAccount;
use App\Models\OnlineTimeClaim;
use App\Models\WalletTransaction;
use App\Payments\CreditPackageStatus;
use App\Payments\PaymentGatewayManager;
use App\Shop\EconomySettingReader;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Displays the authenticated account's Credits balance and ledger history.
 */
final class CreditController extends Controller
{
    /**
     * Returns an ownership-scoped, paginated Credits ledger.
     */
    public function __invoke(
        Request $request,
        PaymentGatewayManager $gateways,
        EconomySettingReader $economySettingReader,
        AccountReadRepository $accountReadRepository,
        ItemStorageRepository $itemStorageRepository,
        ItemExchangeMatcher $itemExchangeMatcher,
        CreditExchangeOfferPresenter $offerPresenter,
    ): View {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $wallet = AccountWallet::query()
            ->where('account_id', $account->username)
            ->where('currency', WalletCurrency::Credits)
            ->first();
        $transactions = WalletTransaction::query()
            ->where('account_id', $account->username)
            ->where('currency', WalletCurrency::Credits)
            ->latest('id')
            ->paginate(25);
        $economySettings = $economySettingReader->current();
        $exchangeOffers = $economySettings->itemExchangeEnabled
            ? CreditExchangeOffer::query()
                ->where('game_profile_key', (string) config('game.profile.key'))
                ->where('enabled', true)
                ->whereNull('archived_at')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : collect();
        $awardedToday = CreditItemExchange::query()
            ->where('account_id', $account->username)
            ->whereDate('created_at', today())
            ->whereIn('status', [
                CreditExchangeStatus::Pending,
                CreditExchangeStatus::Extracted,
                CreditExchangeStatus::Completed,
            ])
            ->selectRaw('credit_exchange_offer_id, SUM(credits_awarded) AS awarded_credits')
            ->groupBy('credit_exchange_offer_id')
            ->pluck('awarded_credits', 'credit_exchange_offer_id');
        $characterExchangeOptions = [];

        if ($exchangeOffers->isNotEmpty()) {
            foreach ($accountReadRepository->charactersFor($account->username) as $character) {
                $storage = $itemStorageRepository->forAccountAndCharacter(
                    $account->username,
                    $character->name,
                );
                $offerCounts = [];

                foreach ($exchangeOffers as $offer) {
                    $offerCounts[$offer->public_id] = $storage === null
                        ? 0
                        : count(array_filter(
                            $storage->character->inventory,
                            static fn ($item): bool => $itemExchangeMatcher->matches($item, $offer),
                        ));
                }

                $characterExchangeOptions[] = [
                    'name' => $character->name,
                    'online' => $character->online,
                    'offerCounts' => $offerCounts,
                ];
            }
        }
        $presentedExchangeOffers = [];

        foreach ($exchangeOffers as $offer) {
            $presentedExchangeOffers[] = [
                'model' => $offer,
                'item' => $offerPresenter->present($offer),
                'awardedToday' => (int) ($awardedToday[$offer->getKey()] ?? 0),
            ];
        }
        $onlineTimeAccount = OnlineTimeAccount::query()->find($account->username);

        return view('account.credits', [
            'balance' => $wallet?->balance ?? 0,
            'transactions' => $transactions,
            'packages' => CreditPackage::query()->where('status', CreditPackageStatus::Published)->orderBy('sort_order')->get(),
            'providers' => $gateways->enabledProviders(),
            'payments' => CreditPayment::query()->where('account_id', $account->username)->latest('id')->limit(10)->get(),
            'economySettings' => $economySettings,
            'exchangeOffers' => $presentedExchangeOffers,
            'characterExchangeOptions' => $characterExchangeOptions,
            'onlineTimeAccount' => $onlineTimeAccount,
            'onlineTimeClaims' => OnlineTimeClaim::query()
                ->where('account_id', $account->username)
                ->latest('id')
                ->limit(10)
                ->get(),
            'itemExchanges' => CreditItemExchange::query()
                ->where('account_id', $account->username)
                ->where('status', CreditExchangeStatus::Completed)
                ->latest('id')
                ->limit(10)
                ->get(),
            'onlineTimeClaimIdempotencyKey' => (string) Str::uuid(),
        ]);
    }
}
