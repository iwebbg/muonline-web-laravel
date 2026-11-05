<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\PurchaseShopOfferRequest;
use App\Models\ShopOffer;
use App\Shop\Actions\PurchaseShopOffer;
use App\Shop\Exceptions\ShopPurchaseRejected;
use App\Shop\ShopOfferType;
use Illuminate\Http\RedirectResponse;

/**
 * Purchases one Web Shop offer for the authenticated account.
 */
final class ShopPurchaseController extends Controller
{
    /**
     * Charges Credits once and redirects the player to durable Rewards.
     */
    public function __invoke(
        PurchaseShopOfferRequest $request,
        ShopOffer $shopOffer,
        PurchaseShopOffer $purchaseShopOffer,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $expectedType = $request->routeIs('shop.items.purchase')
            ? ShopOfferType::Item
            : ShopOfferType::MysteryBox;

        abort_unless($shopOffer->type === $expectedType, 404);

        try {
            $purchaseShopOffer->execute(
                accountId: $account->username,
                offer: $shopOffer,
                idempotencyKey: $request->string('idempotency_key')->toString(),
            );
        } catch (ShopPurchaseRejected $exception) {
            return back()->withErrors([
                'shop_purchase' => __('ui.shop.purchase_errors.'.$exception->reason->value),
            ]);
        }

        return redirect()
            ->route('account.rewards.index')
            ->with('status', __('ui.shop.purchase_completed'));
    }
}
