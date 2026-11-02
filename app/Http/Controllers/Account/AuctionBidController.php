<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\PlaceAuctionBidRequest;
use App\Market\Actions\PlaceAuctionBid;
use App\Market\Exceptions\MarketListingRejected;
use App\Models\MarketListing;
use Illuminate\Http\RedirectResponse;

/**
 * Places auction bids for authenticated game accounts.
 */
final class AuctionBidController extends Controller
{
    /**
     * Applies one idempotent bid and reports its new leading amount.
     */
    public function __invoke(
        PlaceAuctionBidRequest $request,
        MarketListing $marketListing,
        PlaceAuctionBid $placeAuctionBid,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $placeAuctionBid->execute(
                bidderAccountId: $account->username,
                listingId: (int) $marketListing->getKey(),
                amountCredits: $request->integer('amount_credits'),
                idempotencyKey: $request->string('idempotency_key')->toString(),
            );
        } catch (MarketListingRejected $exception) {
            return back()->withErrors([
                'market' => __('ui.market.errors.'.$exception->reason->value),
            ]);
        }

        return back()->with('marketStatus', __('ui.market.bid_placed'));
    }
}
