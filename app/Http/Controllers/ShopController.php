<?php

namespace App\Http\Controllers;

use App\Auth\GameAccount;
use App\Economy\WalletCurrency;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Http\AccountPanelConfiguration;
use App\Models\AccountWallet;
use App\Models\ShopOffer;
use App\Models\ShopOfferItem;
use App\Shop\ShopClassScope;
use App\Shop\ShopItemPresenter;
use App\Shop\ShopOfferType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Displays one authenticated account shop catalog without revealing box contents.
 */
final class ShopController extends Controller
{
    /**
     * Creates the catalog controller with trusted item metadata and tooltip labels.
     */
    public function __construct(
        private readonly ShopItemPresenter $shopItemPresenter,
        private readonly AccountPanelConfiguration $accountPanelConfiguration,
    ) {}

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
        $versionProfile = app(GameVersionProfile::class);
        $classScopes = $versionProfile->shopClassScopes();
        $allowedClassScopes = array_column($classScopes, 'value');
        $classScope = ShopClassScope::tryFrom($request->string('class')->toString());

        if (! $classScope instanceof ShopClassScope || ! in_array($classScope->value, $allowedClassScopes, true)) {
            $classScope = ShopClassScope::All;
        }
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
        $itemPresentations = [];

        foreach ($offers as $offer) {
            $purchaseKeys[$offer->public_id] = (string) Str::uuid();

            if ($type === ShopOfferType::Item) {
                $item = $offer->items->first();

                if ($item instanceof ShopOfferItem) {
                    $itemPresentations[$offer->public_id] = $this->shopItemPresenter->present(
                        $item->snapshot(),
                    );
                }
            }
        }

        return view('shop.index', [
            'type' => $type,
            'offers' => $offers,
            'classScopes' => $classScopes,
            'selectedClassScope' => $classScope,
            'balance' => (int) $balance,
            'purchaseKeys' => $purchaseKeys,
            'itemPresentations' => $itemPresentations,
            'itemOptionLabels' => $this->accountPanelConfiguration->itemOptionLabels(),
        ]);
    }
}
