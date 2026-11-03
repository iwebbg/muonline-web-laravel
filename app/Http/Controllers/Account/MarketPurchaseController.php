<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\PurchaseMarketListingRequest;
use App\Market\Actions\PurchaseMarketListing;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingStatus;
use App\Market\MarketStorageDestination;
use App\Models\MarketListing;
use Illuminate\Http\RedirectResponse;

/**
 * Purchases one active player-market listing for the authenticated game account.
 */
final class MarketPurchaseController extends Controller
{
    /**
     * Applies an idempotent purchase and reports immediate or queued delivery.
     */
    public function __invoke(
        PurchaseMarketListingRequest $request,
        MarketListing $marketListing,
        PurchaseMarketListing $purchaseMarketListing,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $listing = $purchaseMarketListing->execute(
                buyerAccountId: $account->username,
                listingId: (int) $marketListing->getKey(),
                idempotencyKey: $request->string('idempotency_key')->toString(),
            );
        } catch (MarketListingRejected $exception) {
            return back()->withErrors([
                'market' => __('ui.market.errors.'.$exception->reason->value),
            ]);
        }

        $messageKey = match (true) {
            $listing->status !== MarketListingStatus::Delivered => 'ui.market.purchase_pending',
            $listing->storage_destination === MarketStorageDestination::WebVault => 'ui.market.purchase_delivered_web',
            default => 'ui.market.purchase_delivered',
        };

        return back()->with('marketStatus', __($messageKey));
    }
}
