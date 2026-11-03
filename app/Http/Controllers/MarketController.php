<?php

namespace App\Http\Controllers;

use App\Auth\GameAccount;
use App\Http\AccountPanelConfiguration;
use App\Http\Requests\Market\BrowseMarketRequest;
use App\Market\MarketItemCategory;
use App\Market\MarketListingStatus;
use App\Market\MarketSaleType;
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
            ->withQueryString();
        $account = $request->user();
        $myListings = collect();
        $myPurchases = collect();
        $purchaseIdempotencyKeys = [];
        $bidIdempotencyKeys = [];
        $currentAccountId = $account instanceof GameAccount ? $account->username : null;

        foreach ($listings as $listing) {
            $purchaseIdempotencyKeys[$listing->getKey()] = (string) Str::uuid();
            $bidIdempotencyKeys[$listing->getKey()] = (string) Str::uuid();
        }

        if ($account instanceof GameAccount) {
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
            'myListings' => $myListings,
            'myPurchases' => $myPurchases,
            'purchaseIdempotencyKeys' => $purchaseIdempotencyKeys,
            'bidIdempotencyKeys' => $bidIdempotencyKeys,
            'gameWritesEnabled' => (bool) config('game.writes_enabled', false)
                && ! (bool) config('database.connections.game.readonly', true),
            'currentAccountId' => $currentAccountId,
            'auctionEnabled' => $auctionEnabled,
            'marketCategories' => MarketItemCategory::cases(),
            'marketSearchTerm' => $searchTerm,
            'selectedMarketCategory' => $category,
            'marketServerNow' => now()->toAtomString(),
            'itemOptionLabels' => $this->accountPanelConfiguration->itemOptionLabels(),
        ]);
    }
}
