<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Economy\WalletCurrency;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Game\Contracts\MarketItemEscrowRepository;
use App\Market\Actions\PlaceAuctionBid;
use App\Market\Actions\SettleExpiredAuction;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketEscrowResult;
use App\Market\MarketListingBlockReason;
use App\Market\MarketListingStatus;
use App\Market\MarketSaleType;
use App\Models\AccountWallet;
use App\Models\AuctionBid;
use App\Models\MarketListing;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies auction holds, outbid releases and expiry settlement.
 */
final class AuctionBidTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Enables mocked game writes for auction settlement tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);
    }

    /**
     * Confirms a new leader's full bid is held exactly once.
     */
    public function test_first_bid_holds_credits_and_becomes_leader(): void
    {
        $listing = $this->auction('seller01');
        $this->wallet('bidder001', 500);
        $this->mockAccounts('bidder001');

        $bid = $this->placeBid('bidder001', $listing, 150, '9c77dc7d-f70e-4d1f-945c-3757d46cd8b2');

        $this->assertSame(350, $this->balance('bidder001'));
        $this->assertSame(150, $bid->amount_credits);
        $this->assertSame('bidder001', $listing->refresh()->highest_bidder_account_id);
        $this->assertSame($bid->getKey(), $listing->highest_bid_id);
    }

    /**
     * Confirms a higher bid atomically releases the previous leader's hold.
     */
    public function test_outbid_releases_previous_hold_in_same_transaction(): void
    {
        $listing = $this->auction('seller02');
        $this->wallet('bidder002', 500);
        $this->wallet('bidder003', 500);
        $this->mockAccounts('bidder002', 'bidder003', 'bidder002');
        $first = $this->placeBid('bidder002', $listing, 150, '8acf7f7c-a94a-4754-b721-f1150afea2f0');

        $second = $this->placeBid('bidder003', $listing, 175, '35395a01-6703-471e-8931-f732dc963868');

        $this->assertSame(500, $this->balance('bidder002'));
        $this->assertSame(325, $this->balance('bidder003'));
        $this->assertNotNull($first->refresh()->outbid_at);
        $this->assertNotNull($first->release_wallet_transaction_id);
        $this->assertSame($second->getKey(), $listing->refresh()->highest_bid_id);
        $this->assertSame(3, WalletTransaction::query()->count());
    }

    /**
     * Confirms a low bid never changes balances or the leading offer.
     */
    public function test_bid_below_increment_is_rejected_without_wallet_change(): void
    {
        $listing = $this->auction('seller03');
        $this->wallet('bidder004', 500);

        try {
            $this->placeBid('bidder004', $listing, 99, 'c74a99d6-664a-4f69-8a8d-6269242a7822');
            $this->fail('An auction bid below the minimum must be rejected.');
        } catch (MarketListingRejected $exception) {
            $this->assertSame(MarketListingBlockReason::BidTooLow, $exception->reason);
        }

        $this->assertSame(500, $this->balance('bidder004'));
        $this->assertSame(0, AuctionBid::query()->count());
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    /**
     * Confirms replaying an identical bid never creates a second Credits hold.
     */
    public function test_identical_bid_replay_returns_original_hold_once(): void
    {
        $listing = $this->auction('seller05');
        $this->wallet('bidder006', 500);
        $this->mockAccounts('bidder006');
        $key = 'b93ddd6f-232c-4207-8972-eef7a23e7025';

        $first = $this->placeBid('bidder006', $listing, 150, $key);
        $replayed = $this->placeBid('bidder006', $listing, 150, $key);

        $this->assertSame($first->getKey(), $replayed->getKey());
        $this->assertSame(350, $this->balance('bidder006'));
        $this->assertSame(1, AuctionBid::query()->count());
        $this->assertSame(1, WalletTransaction::query()->count());
    }

    /**
     * Confirms an unaffordable bid leaves the auction and wallet unchanged.
     */
    public function test_insufficient_credits_do_not_create_a_bid(): void
    {
        $listing = $this->auction('seller06');
        $this->wallet('bidder007', 50);
        $this->mockAccounts('bidder007');

        try {
            $this->placeBid('bidder007', $listing, 100, '37118947-9e2c-4f3e-a6fa-f84e793f220f');
            $this->fail('An auction bid without sufficient Credits must be rejected.');
        } catch (MarketListingRejected $exception) {
            $this->assertSame(MarketListingBlockReason::InsufficientCredits, $exception->reason);
        }

        $this->assertSame(50, $this->balance('bidder007'));
        $this->assertSame(0, AuctionBid::query()->count());
        $this->assertNull($listing->refresh()->highest_bid_id);
    }

    /**
     * Confirms expiry credits the seller from the winning hold and delivers once.
     */
    public function test_expired_auction_settles_winner_and_delivers_item(): void
    {
        $listing = $this->auction('seller04');
        $this->wallet('bidder005', 500);
        $this->mockAccounts('bidder005', 'seller04');
        $bid = $this->placeBid('bidder005', $listing, 200, 'f16a734c-a935-4b1a-bd14-c3c5c22d18b0');
        $listing->update(['expires_at' => now()->subSecond()]);
        $this->mock(MarketItemEscrowRepository::class)
            ->shouldReceive('deposit')
            ->once()
            ->with('bidder005', $listing->item_binary, 1, 2)
            ->andReturn(MarketEscrowResult::Deposited);

        $settled = $this->app->make(SettleExpiredAuction::class)->execute((int) $listing->getKey());

        $this->assertSame(MarketListingStatus::Delivered, $settled->status);
        $this->assertSame('bidder005', $settled->buyer_account_id);
        $this->assertSame($bid->hold_wallet_transaction_id, $settled->buyer_wallet_transaction_id);
        $this->assertSame(300, $this->balance('bidder005'));
        $this->assertSame(200, $this->balance('seller04'));
    }

    /**
     * Confirms an auction with no bids returns its item through the scheduler.
     */
    public function test_expired_auction_without_bids_returns_item_to_seller(): void
    {
        $listing = $this->auction('seller07');
        $listing->update(['expires_at' => now()->subSecond()]);
        $this->mock(MarketItemEscrowRepository::class)
            ->shouldReceive('deposit')
            ->once()
            ->with('seller07', $listing->item_binary, 1, 2)
            ->andReturn(MarketEscrowResult::Deposited);

        $this->artisan('market:sync-escrow')
            ->expectsOutputToContain('1 synced, 0 pending retry')
            ->assertSuccessful();

        $this->assertSame(MarketListingStatus::Returned, $listing->refresh()->status);
        $this->assertNotNull($listing->settled_at);
    }

    /**
     * Confirms the authenticated HTTP endpoint places a CSRF-protected bid.
     */
    public function test_authenticated_account_places_bid_through_market_endpoint(): void
    {
        $listing = $this->auction('seller08');
        $this->wallet('bidder008', 300);
        $this->mockAccounts('bidder008');
        $csrfToken = 'auction-bid-endpoint-token';

        $response = $this
            ->actingAs($this->account('bidder008'))
            ->withSession(['_token' => $csrfToken])
            ->from('/market')
            ->post(route('market.listings.bids.store', $listing), [
                '_token' => $csrfToken,
                'amount_credits' => 125,
                'idempotency_key' => '39b28926-45fc-4108-9b60-c6d58a54fc0c',
            ]);

        $response
            ->assertRedirect('/market')
            ->assertSessionHas('marketStatus');
        $this->assertSame(175, $this->balance('bidder008'));
        $this->assertSame('bidder008', $listing->refresh()->highest_bidder_account_id);
    }

    /**
     * Creates one active timed auction with stable bidding rules.
     */
    private function auction(string $sellerAccountId): MarketListing
    {
        return MarketListing::factory()->create([
            'seller_account_id' => $sellerAccountId,
            'sale_type' => MarketSaleType::Auction,
            'price_credits' => 100,
            'minimum_bid_credits' => 100,
            'bid_increment_credits' => 25,
            'expires_at' => now()->addHour(),
        ]);
    }

    /**
     * Creates one Credits wallet with a known available balance.
     */
    private function wallet(string $accountId, int $balance): void
    {
        AccountWallet::factory()->create([
            'account_id' => $accountId,
            'currency' => WalletCurrency::Credits,
            'balance' => $balance,
        ]);
    }

    /**
     * Returns one account's Credits balance.
     */
    private function balance(string $accountId): int
    {
        return (int) AccountWallet::query()
            ->where('account_id', $accountId)
            ->where('currency', WalletCurrency::Credits)
            ->value('balance');
    }

    /**
     * Places one bid through the application action.
     */
    private function placeBid(
        string $bidderAccountId,
        MarketListing $listing,
        int $amountCredits,
        string $idempotencyKey,
    ): AuctionBid {
        return $this->app->make(PlaceAuctionBid::class)->execute(
            bidderAccountId: $bidderAccountId,
            listingId: (int) $listing->getKey(),
            amountCredits: $amountCredits,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * Makes wallet account checks succeed in their expected call order.
     */
    private function mockAccounts(string ...$accountIds): void
    {
        $lookup = $this->mock(GameAccountLookupRepository::class);
        $lookup->shouldReceive('exists')
            ->times(count($accountIds))
            ->andReturnUsing(
                static fn (string $accountId): bool => in_array($accountId, $accountIds, true),
            );
    }

    /**
     * Builds one authenticated legacy account for endpoint verification.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: $username,
            displayName: 'Auction Bidder',
            email: null,
            blocked: false,
            vip: false,
            password: 'secret1',
        );
    }
}
