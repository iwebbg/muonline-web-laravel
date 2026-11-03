<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Market\MarketListingStatus;
use App\Market\MarketSaleType;
use App\Models\MarketListing;
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
