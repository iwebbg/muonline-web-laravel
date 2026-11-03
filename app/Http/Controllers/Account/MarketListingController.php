<?php

namespace App\Http\Controllers\Account;

use App\Auth\GameAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreMarketListingRequest;
use App\Market\Actions\CreateMarketListing;
use App\Market\Actions\ReturnMarketListing;
use App\Market\AuctionDuration;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingStatus;
use App\Market\MarketSaleType;
use App\Market\MarketStorageDestination;
use App\Models\MarketListing;
use App\Modules\ModuleManager;
use App\Modules\ServerModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Creates and returns recoverable player-owned market escrow listings.
 */
final class MarketListingController extends Controller
{
    /**
     * Creates one listing from an exact owned warehouse item.
     */
    public function store(
        StoreMarketListingRequest $request,
        CreateMarketListing $createMarketListing,
        ModuleManager $moduleManager,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        $saleType = MarketSaleType::from($request->string('sale_type')->toString());

        abort_unless(
            $saleType !== MarketSaleType::Auction
            || $moduleManager->enabled(ServerModule::Auction),
            404,
        );

        try {
            $listing = $createMarketListing->execute(
                accountId: $account->username,
                characterName: $request->string('character_name')->toString(),
                vault: $request->integer('vault'),
                slot: $request->integer('slot'),
                priceCredits: $request->integer('price_credits'),
                idempotencyKey: $request->string('idempotency_key')->toString(),
                saleType: $saleType,
                bidIncrementCredits: $request->integer('bid_increment_credits'),
                auctionDuration: AuctionDuration::from(
                    $request->string('auction_duration_hours')->toString(),
                ),
            );
        } catch (MarketListingRejected $exception) {
            return back()->withErrors([
                'market' => __('ui.market.errors.'.$exception->reason->value),
            ]);
        }

        $messageKey = $listing->status === MarketListingStatus::Active
            ? 'ui.market.listing_created'
            : 'ui.market.listing_pending';

        return back()->with('marketStatus', __($messageKey));
    }

    /**
     * Removes one owned unsold listing and queues its original item for return.
     */
    public function destroy(
        Request $request,
        MarketListing $marketListing,
        ReturnMarketListing $returnMarketListing,
    ): RedirectResponse {
        $account = $request->user();

        abort_unless($account instanceof GameAccount, 403);

        try {
            $listing = $returnMarketListing->execute(
                accountId: $account->username,
                listingId: (int) $marketListing->getKey(),
            );
        } catch (MarketListingRejected $exception) {
            return back()->withErrors([
                'market' => __('ui.market.errors.'.$exception->reason->value),
            ]);
        }

        $messageKey = match (true) {
            $listing->status !== MarketListingStatus::Returned => 'ui.market.return_pending',
            $listing->storage_destination === MarketStorageDestination::WebVault => 'ui.market.item_returned_web',
            default => 'ui.market.item_returned',
        };

        return back()->with('marketStatus', __($messageKey));
    }
}
