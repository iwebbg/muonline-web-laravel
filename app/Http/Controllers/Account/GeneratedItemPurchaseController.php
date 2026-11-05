<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\PurchaseGeneratedItemRequest;
use App\Shop\Actions\PurchaseGeneratedItem;
use App\Shop\Exceptions\ShopPurchaseRejected;
use Illuminate\Http\RedirectResponse;

/**
 * Purchases one server-priced custom item for the authenticated account.
 */
final class GeneratedItemPurchaseController extends Controller
{
    /**
     * Charges Credits once and redirects the player to durable Rewards.
     */
    public function __invoke(
        PurchaseGeneratedItemRequest $request,
        PurchaseGeneratedItem $purchaseGeneratedItem,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $purchaseGeneratedItem->execute(
                accountId: $account->username,
                options: $request->itemOptions(),
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
