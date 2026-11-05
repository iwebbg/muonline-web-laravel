<?php

namespace App\Http\Controllers;

use App\Auth\GameAccount;
use App\Economy\WalletCurrency;
use App\Models\AccountWallet;
use App\Models\ShopOffer;
use App\Shop\ShopClassScope;
use App\Shop\ShopOfferType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Displays one public shop catalog without revealing mystery-box contents.
 */
final class ShopController extends Controller
{
    /**
     * Returns available offers filtered by compatible class family.
     */
    public function items(Request $request): View
    {
        return $this->catalog($request, ShopOfferType::Item);
    }

    /**
     * Displays the mystery-box catalog without loading its item relationships.
     */
    public function mysteryBoxes(Request $request): View
    {
        return $this->catalog($request, ShopOfferType::MysteryBox);
    }

    /**
     * Builds one paginated catalog for a fixed offer type.
     */
    private function catalog(Request $request, ShopOfferType $type): View
    {
        $classScope = ShopClassScope::tryFrom($request->string('class')->toString());
        $offersQuery = ShopOffer::query()
            ->available()
            ->where('type', $type)
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($type === ShopOfferType::Item) {
            $offersQuery->with('items');
        }

        if ($classScope instanceof ShopClassScope && $classScope !== ShopClassScope::All) {
            $offersQuery->whereIn('class_scope', [ShopClassScope::All, $classScope]);
        }

        $account = $request->user();
        $balance = $account instanceof GameAccount
            ? AccountWallet::query()
                ->where('account_id', $account->username)
                ->where('currency', WalletCurrency::Credits)
                ->value('balance') ?? 0
            : 0;

        $offers = $offersQuery->paginate(18)->withQueryString();
        $purchaseKeys = [];

        foreach ($offers as $offer) {
            $purchaseKeys[$offer->public_id] = (string) Str::uuid();
        }

        return view('shop.index', [
            'type' => $type,
            'offers' => $offers,
            'classScopes' => ShopClassScope::cases(),
            'selectedClassScope' => $classScope ?? ShopClassScope::All,
            'balance' => (int) $balance,
            'purchaseKeys' => $purchaseKeys,
        ]);
    }
}
