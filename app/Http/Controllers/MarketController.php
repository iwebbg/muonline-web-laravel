<?php

namespace App\Http\Controllers;

use App\Auth\GameAccount;
use App\Market\MarketListingStatus;
use App\Market\MarketSaleType;
use App\Models\MarketListing;
use App\Modules\ModuleManager;
use App\Modules\ServerModule;
use Illuminate\Http\Request;
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
    ) {}

    /**
     * Displays active listings and the current account's market history.
     */
    public function __invoke(Request $request): View
    {
        $auctionEnabled = $this->moduleManager->enabled(ServerModule::Auction);
        $listingsQuery = MarketListing::query()
            ->where('status', MarketListingStatus::Active)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if (! $auctionEnabled) {
            $listingsQuery->where('sale_type', '!=', MarketSaleType::Auction);
        }

        $listings = $listingsQuery
            ->latest('activated_at')
            ->paginate(24);
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
        ]);
    }
}
