<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Market\MarketListingStatus;
use App\Market\MarketSaleType;
use App\Models\AuctionBid;
use App\Models\MarketListing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies public market rendering and authenticated seller data isolation.
 */
final class MarketPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms public listings render safely without exposing seller controls.
     */
    public function test_guest_can_browse_escaped_active_listings(): void
    {
        MarketListing::factory()->create([
            'item_snapshot' => [
                'name' => '<script>alert("market")</script>',
                'image' => null,
                'code' => '0:0',
                'level' => 0,
                'rarity' => 'normal',
                'width' => 1,
                'height' => 1,
            ],
        ]);

        $response = $this->get('/market');

        $response
            ->assertOk()
            ->assertSee('Player market')
            ->assertDontSee('<script>alert("market")</script>', false)
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('Warehouse to escrow');
    }

    /**
     * Confirms seller history is scoped to the authenticated game account.
     */
    public function test_authenticated_market_page_shows_only_owned_seller_history(): void
    {
        MarketListing::factory()->create([
            'seller_account_id' => 'owner',
            'item_snapshot' => ['name' => 'Owned Market Item'],
        ]);
        MarketListing::factory()->create([
            'seller_account_id' => 'other',
            'item_snapshot' => ['name' => 'Other Private History'],
            'status' => MarketListingStatus::Returned,
        ]);
        $response = $this
            ->actingAs($this->account('owner'))
            ->get('/market');

        $response
            ->assertOk()
            ->assertDontSee('Warehouse to escrow')
            ->assertSee(route('account.dashboard', ['tab' => 'vaults']), false)
            ->assertSee('Owned Market Item')
            ->assertDontSee('Other Private History');
    }

    /**
     * Confirms the current-bids workspace stays hidden without active participation.
     */
    public function test_authenticated_market_page_hides_empty_current_bids_section(): void
    {
        $response = $this
            ->actingAs($this->account('owner'))
            ->get(route('market.index'));

        $response
            ->assertOk()
            ->assertDontSee('Your current bids')
            ->assertDontSee('You are not participating in an active auction yet.')
            ->assertDontSee('id="your-current-bids"', false);
    }

    /**
     * Confirms item-name search limits public results and preserves escaped rendering.
     */
    public function test_market_can_search_active_listings_by_item_name(): void
    {
        MarketListing::factory()->create([
            'item_snapshot' => [
                'name' => 'Red Phoenix Armor',
                'code' => '8:21',
                'rarity' => 'excellent',
            ],
        ]);
        MarketListing::factory()->create([
            'item_snapshot' => [
                'name' => 'Satan Wings',
                'code' => '12:5',
                'rarity' => 'normal',
            ],
        ]);

        $response = $this->get(route('market.index', ['q' => 'Phoenix']));

        $response
            ->assertOk()
            ->assertSee('Red Phoenix Armor')
            ->assertDontSee('Satan Wings')
            ->assertSee('value="Phoenix"', false);
    }

    /**
     * Confirms category filters use stable MuOnline item group codes.
     */
    public function test_market_can_filter_active_listings_by_item_category(): void
    {
        MarketListing::factory()->create([
            'item_snapshot' => [
                'name' => 'Destruction Sword',
                'code' => '0:21',
                'rarity' => 'normal',
            ],
        ]);
        MarketListing::factory()->create([
            'item_snapshot' => [
                'name' => 'Legendary Armor',
                'code' => '8:3',
                'rarity' => 'normal',
            ],
        ]);

        $response = $this->get(route('market.index', ['category' => 'weapons']));

        $response
            ->assertOk()
            ->assertSee('Destruction Sword')
            ->assertDontSee('Legendary Armor')
            ->assertSee('value="weapons" selected', false);
    }

    /**
     * Confirms auctions expose a friendly live deadline and an exact localized time.
     */
    public function test_auction_deadline_has_relative_and_exact_time_presentations(): void
    {
        $this->travelTo('2026-11-03 12:00:00');
        MarketListing::factory()->create([
            'sale_type' => MarketSaleType::Auction,
            'expires_at' => now()->addDays(2),
            'item_snapshot' => [
                'name' => 'Deadline Wings',
                'code' => '12:5',
                'rarity' => 'normal',
            ],
        ]);

        $response = $this->get(route('market.index'));

        $response
            ->assertOk()
            ->assertSee('Auction ends')
            ->assertSee('05 Nov 2026 · 12:00')
            ->assertSee('marketExpiry(', false);
    }

    /**
     * Confirms an account can distinguish its leading and outbid auction offers.
     */
    public function test_authenticated_market_page_summarizes_current_auction_bids(): void
    {
        $outbidListing = MarketListing::factory()->create([
            'sale_type' => MarketSaleType::Auction,
            'minimum_bid_credits' => 100,
            'bid_increment_credits' => 25,
            'expires_at' => now()->addDay(),
            'item_snapshot' => ['name' => 'Outbid Market Wings'],
        ]);
        AuctionBid::factory()->for($outbidListing, 'listing')->create([
            'bidder_account_id' => 'bid-owner',
            'amount_credits' => 125,
            'outbid_at' => now(),
        ]);
        $winningBid = AuctionBid::factory()->for($outbidListing, 'listing')->create([
            'bidder_account_id' => 'other-user',
            'amount_credits' => 175,
        ]);
        $outbidListing->update([
            'highest_bid_credits' => 175,
            'highest_bidder_account_id' => 'other-user',
            'highest_bid_id' => $winningBid->getKey(),
        ]);

        $leadingListing = MarketListing::factory()->create([
            'sale_type' => MarketSaleType::Auction,
            'minimum_bid_credits' => 100,
            'bid_increment_credits' => 25,
            'expires_at' => now()->addDays(2),
            'item_snapshot' => ['name' => 'Leading Market Armor'],
        ]);
        $leadingBid = AuctionBid::factory()->for($leadingListing, 'listing')->create([
            'bidder_account_id' => 'bid-owner',
            'amount_credits' => 225,
        ]);
        $leadingListing->update([
            'highest_bid_credits' => 225,
            'highest_bidder_account_id' => 'bid-owner',
            'highest_bid_id' => $leadingBid->getKey(),
        ]);

        $unrelatedListing = MarketListing::factory()->create([
            'sale_type' => MarketSaleType::Auction,
            'minimum_bid_credits' => 100,
            'bid_increment_credits' => 25,
            'expires_at' => now()->addDays(3),
            'item_snapshot' => ['name' => 'Unrelated Private Bid'],
        ]);
        $unrelatedBid = AuctionBid::factory()->for($unrelatedListing, 'listing')->create([
            'bidder_account_id' => 'outsider',
            'amount_credits' => 150,
        ]);
        $unrelatedListing->update([
            'highest_bid_credits' => 150,
            'highest_bidder_account_id' => 'outsider',
            'highest_bid_id' => $unrelatedBid->getKey(),
        ]);

        $response = $this
            ->actingAs($this->account('bid-owner'))
            ->get(route('market.index'));

        $response
            ->assertOk()
            ->assertSee('Your current bids')
            ->assertSee('Outbid Market Wings')
            ->assertSee('You have been outbid')
            ->assertSee('Your latest bid')
            ->assertSee('125 Credits')
            ->assertSee('175 Credits')
            ->assertSee('Leading Market Armor')
            ->assertSee('You are leading')
            ->assertSee('225 Credits')
            ->assertSee(route('market.listings.bids.store', $outbidListing), false)
            ->assertViewHas('currentBids', function (mixed $currentBids): bool {
                return $currentBids instanceof LengthAwarePaginator
                    && ! $currentBids->contains(function (MarketListing $listing): bool {
                        return ($listing->item_snapshot['name'] ?? null) === 'Unrelated Private Bid';
                    });
            });
    }

    /**
     * Confirms current account auction participation has an independent paginator.
     */
    public function test_current_auction_bids_are_paginated_independently(): void
    {
        $listings = MarketListing::factory()->count(9)->create([
            'sale_type' => MarketSaleType::Auction,
            'minimum_bid_credits' => 100,
            'bid_increment_credits' => 25,
            'expires_at' => now()->addDay(),
        ]);

        foreach ($listings as $listing) {
            $bid = AuctionBid::factory()->for($listing, 'listing')->create([
                'bidder_account_id' => 'bid-owner',
                'amount_credits' => 125,
            ]);
            $listing->update([
                'highest_bid_credits' => 125,
                'highest_bidder_account_id' => 'bid-owner',
                'highest_bid_id' => $bid->getKey(),
            ]);
        }

        $response = $this
            ->actingAs($this->account('bid-owner'))
            ->get(route('market.index'));

        $response
            ->assertOk()
            ->assertViewHas('currentBids', function (mixed $currentBids): bool {
                return $currentBids instanceof LengthAwarePaginator
                    && $currentBids->total() === 9
                    && $currentBids->count() === 8
                    && $currentBids->getPageName() === 'bids_page';
            })
            ->assertSee('?bids_page=2#your-current-bids', false);
    }

    /**
     * Confirms the public listing grid remains bounded and links back to its section.
     */
    public function test_active_market_listings_are_paginated(): void
    {
        MarketListing::factory()->count(25)->create();

        $response = $this->get(route('market.index'));

        $response
            ->assertOk()
            ->assertViewHas('listings', function (mixed $listings): bool {
                return $listings instanceof LengthAwarePaginator
                    && $listings->total() === 25
                    && $listings->count() === 24;
            })
            ->assertSee('?page=2#active-listings', false);
    }

    /**
     * Builds one authenticated game account for seller workspace checks.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: $username,
            displayName: 'Market Test',
            email: null,
            blocked: false,
            vip: false,
            password: 'secret1',
        );
    }
}
