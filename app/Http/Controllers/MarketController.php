<?php

namespace App\Http\Controllers;

use App\Auth\GameAccount;
use App\Http\AccountPanelConfiguration;
use App\Http\Requests\Market\BrowseMarketRequest;
use App\Market\MarketItemCategory;
use App\Market\MarketListingStatus;
use App\Market\MarketSaleType;
use App\Models\AuctionBid;
use App\Models\GameServer;
use App\Models\MarketListing;
use App\Modules\ModuleManager;
use App\Modules\ServerModule;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Displays public market escrow listings and the authenticated seller workspace.
 */
final class MarketController extends Controller
{
    /**
     * Creates the market page with module-aware auction behavior.
     */
    public function __construct(
        private readonly ModuleManager $moduleManager,
        private readonly AccountPanelConfiguration $accountPanelConfiguration,
    ) {}

    /**
     * Displays active listings and the current account's market history.
     */
    public function __invoke(BrowseMarketRequest $request): View
    {
        $auctionEnabled = $this->moduleManager->enabled(ServerModule::Auction);
        $searchTerm = $request->searchTerm();
        $category = $request->category();
        $listingsQuery = MarketListing::query()
            ->where('status', MarketListingStatus::Active)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if (! $auctionEnabled) {
            $listingsQuery->where('sale_type', '!=', MarketSaleType::Auction);
        }

        if ($searchTerm !== null) {
            $listingsQuery->where(
                'item_snapshot->name',
                'like',
                '%'.$searchTerm.'%',
            );
        }

        $category?->applyTo($listingsQuery);

        $listings = $listingsQuery
            ->latest('activated_at')
            ->latest('id')
            ->paginate(24)
            ->withQueryString()
            ->fragment('active-listings');
        $account = $request->user();
        $currentBids = collect();
        $myListings = collect();
        $myPurchases = collect();
        $purchaseIdempotencyKeys = [];
        $bidIdempotencyKeys = [];
        $currentBidIdempotencyKeys = [];
        $currentAccountId = $account instanceof GameAccount ? $account->username : null;

        foreach ($listings as $listing) {
            $purchaseIdempotencyKeys[$listing->getKey()] = (string) Str::uuid();
            $bidIdempotencyKeys[$listing->getKey()] = (string) Str::uuid();
        }

        if ($account instanceof GameAccount) {
            if ($auctionEnabled) {
                $latestBidAmount = AuctionBid::query()
                    ->select('amount_credits')
                    ->whereColumn('market_listing_id', 'market_listings.id')
                    ->where('bidder_account_id', $account->username)
                    ->latest('id')
                    ->limit(1);
                $latestBidAt = AuctionBid::query()
                    ->select('created_at')
                    ->whereColumn('market_listing_id', 'market_listings.id')
                    ->where('bidder_account_id', $account->username)
                    ->latest('id')
                    ->limit(1);
                $currentBids = MarketListing::query()
                    ->addSelect([
                        'current_account_last_bid_credits' => $latestBidAmount,
                        'current_account_last_bid_at' => $latestBidAt,
                    ])
                    ->withCasts([
                        'current_account_last_bid_credits' => 'integer',
                        'current_account_last_bid_at' => 'immutable_datetime',
                    ])
                    ->where('sale_type', MarketSaleType::Auction)
                    ->where('status', MarketListingStatus::Active)
                    ->where('expires_at', '>', now())
                    ->whereHas('bids', function ($query) use ($account): void {
                        $query->where('bidder_account_id', $account->username);
                    })
                    ->orderBy('expires_at')
                    ->orderBy('id')
                    ->paginate(8, ['*'], 'bids_page')
                    ->withQueryString()
                    ->fragment('your-current-bids');

                foreach ($currentBids as $listing) {
                    $currentBidIdempotencyKeys[$listing->getKey()] = (string) Str::uuid();
                }
            }

            $myListings = MarketListing::query()
                ->where('seller_account_id', $account->username)
                ->latest('id')
                ->limit(50)
                ->get();
            $myPurchases = MarketListing::query()
                ->where('buyer_account_id', $account->username)
                ->latest('purchased_at')
                ->limit(50)
                ->get();
        }

        return view('market.index', [
            'listings' => $listings,
            'currentBids' => $currentBids,
            'myListings' => $myListings,
            'myPurchases' => $myPurchases,
            'purchaseIdempotencyKeys' => $purchaseIdempotencyKeys,
            'bidIdempotencyKeys' => $bidIdempotencyKeys,
            'currentBidIdempotencyKeys' => $currentBidIdempotencyKeys,
            'gameWritesEnabled' => (bool) config('game.writes_enabled', false)
                && ! (bool) config('database.connections.game.readonly', true),
            'currentAccountId' => $currentAccountId,
            'auctionEnabled' => $auctionEnabled,
            'marketCategories' => MarketItemCategory::cases(),
            'marketSearchTerm' => $searchTerm,
            'selectedMarketCategory' => $category,
            'marketServerNow' => now()->toAtomString(),
            'marketServerVersion' => $this->currentServerVersion(),
            'itemOptionLabels' => $this->accountPanelConfiguration->itemOptionLabels(),
        ]);
    }

    /**
     * Resolves the administrator-managed version for the current website server.
     */
    private function currentServerVersion(): string
    {
        $currentServerVersion = GameServer::query()
            ->where('is_current', true)
            ->value('version');

        return is_string($currentServerVersion) && trim($currentServerVersion) !== ''
            ? $currentServerVersion
            : (string) config('game.profile.version');
    }
}
