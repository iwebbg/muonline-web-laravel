<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Market\MarketListingStatus;
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
