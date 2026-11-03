<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Game\Contracts\MarketItemEscrowRepository;
use App\Market\Actions\PurchaseMarketListing;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketEscrowResult;
use App\Market\MarketListingBlockReason;
use App\Market\MarketListingStatus;
use App\Market\MarketStorageDestination;
use App\Models\AccountWallet;
use App\Models\MarketListing;
use App\Models\WalletTransaction;
use App\Models\WebStorageItem;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifies atomic market payments, replay safety and retryable item delivery.
 */
final class MarketPurchaseTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Enables the explicit game-write switches for mocked warehouse delivery.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);
    }

    /**
     * Confirms Credits and ownership transfer atomically before exact item delivery.
     */
    public function test_purchase_transfers_credits_and_delivers_original_item(): void
    {
        $listing = MarketListing::factory()->create([
            'seller_account_id' => 'seller01',
            'price_credits' => 200,
        ]);
        AccountWallet::factory()->create([
            'account_id' => 'buyer001',
            'currency' => WalletCurrency::Credits,
            'balance' => 500,
        ]);
        $this->mockExistingAccounts('buyer001', 'seller01');
        $this->mock(MarketItemEscrowRepository::class)
            ->shouldReceive('deposit')
            ->once()
            ->with('buyer001', $listing->item_binary, 1, 2)
            ->andReturn(MarketEscrowResult::Deposited);

        $purchased = $this->purchase(
            buyerAccountId: 'buyer001',
            listingId: (int) $listing->getKey(),
            idempotencyKey: 'bf36a6d8-925e-4917-b5a3-3d8c8472474c',
        );

        $this->assertSame(MarketListingStatus::Delivered, $purchased->status);
        $this->assertSame(MarketStorageDestination::GameVault, $purchased->storage_destination);
        $this->assertSame('buyer001', $purchased->buyer_account_id);
        $this->assertNull($purchased->escrow_key);
        $this->assertNotNull($purchased->delivered_at);
        $this->assertSame(300, $this->balance('buyer001'));
        $this->assertSame(200, $this->balance('seller01'));
        $this->assertEqualsCanonicalizing(
            [WalletTransactionType::Purchase, WalletTransactionType::Sale],
            WalletTransaction::query()->pluck('type')->all(),
        );
    }

    /**
     * Confirms a full game vault delivers the exact purchased item to web storage.
     */
    public function test_full_game_vault_falls_back_to_buyer_web_storage(): void
    {
        $listing = MarketListing::factory()->create([
            'seller_account_id' => 'seller09',
            'price_credits' => 200,
        ]);
        AccountWallet::factory()->create([
            'account_id' => 'buyer009',
            'currency' => WalletCurrency::Credits,
            'balance' => 500,
        ]);
        $this->mockExistingAccounts('buyer009', 'seller09');
        $this->mock(MarketItemEscrowRepository::class)
            ->shouldReceive('deposit')
            ->once()
            ->andThrow(new MarketListingRejected(MarketListingBlockReason::StorageFull));

        $purchased = $this->purchase(
            buyerAccountId: 'buyer009',
            listingId: (int) $listing->getKey(),
            idempotencyKey: '2018d209-eb7c-4ff6-8a8d-f68e874b7c30',
        );
        $storedItem = WebStorageItem::query()->sole();

        $this->assertSame(MarketListingStatus::Delivered, $purchased->status);
        $this->assertSame(MarketStorageDestination::WebVault, $purchased->storage_destination);
        $this->assertNull($purchased->escrow_key);
        $this->assertSame('buyer009', $storedItem->account_id);
        $this->assertSame(WebStorageItemStatus::Available, $storedItem->status);
        $this->assertSame($listing->item_binary, $storedItem->item_binary);
        $this->assertSame($listing->item_fingerprint, $storedItem->item_fingerprint);
        $this->assertSame(300, $this->balance('buyer009'));
        $this->assertSame(200, $this->balance('seller09'));
    }

    /**
     * Confirms an identical replay cannot charge or deliver the purchase twice.
     */
    public function test_identical_replay_returns_the_completed_purchase_once(): void
    {
        $listing = MarketListing::factory()->create([
            'seller_account_id' => 'seller02',
            'price_credits' => 125,
        ]);
        AccountWallet::factory()->create([
            'account_id' => 'buyer002',
            'balance' => 500,
        ]);
        $this->mockExistingAccounts('buyer002', 'seller02');
        $this->mock(MarketItemEscrowRepository::class)
            ->shouldReceive('deposit')
            ->once()
            ->andReturn(MarketEscrowResult::Deposited);
        $arguments = [
            'buyerAccountId' => 'buyer002',
            'listingId' => (int) $listing->getKey(),
            'idempotencyKey' => '00c62e85-2f89-4cca-8036-275f8083c3e9',
        ];

        $first = $this->purchase(...$arguments);
        $replayed = $this->purchase(...$arguments);

        $this->assertSame($first->getKey(), $replayed->getKey());
        $this->assertSame(375, $this->balance('buyer002'));
        $this->assertSame(125, $this->balance('seller02'));
        $this->assertSame(2, WalletTransaction::query()->count());
    }

    /**
     * Confirms insufficient Credits leave the listing and every balance unchanged.
     */
    public function test_insufficient_credits_roll_back_the_entire_purchase(): void
    {
        $listing = MarketListing::factory()->create([
            'seller_account_id' => 'seller03',
            'price_credits' => 500,
        ]);
        AccountWallet::factory()->create([
            'account_id' => 'buyer003',
            'balance' => 50,
        ]);
        $this->mockExistingAccounts('buyer003');
        $this->mock(MarketItemEscrowRepository::class)->shouldNotReceive('deposit');

        try {
            $this->purchase(
                buyerAccountId: 'buyer003',
                listingId: (int) $listing->getKey(),
                idempotencyKey: '24678f1e-37a8-457c-b337-ddefbf411a8c',
            );
            $this->fail('A market purchase without enough Credits must be rejected.');
        } catch (MarketListingRejected $exception) {
            $this->assertSame(MarketListingBlockReason::InsufficientCredits, $exception->reason);
        }

        $this->assertSame(50, $this->balance('buyer003'));
        $this->assertSame(0, WalletTransaction::query()->count());
        $this->assertSame(MarketListingStatus::Active, $listing->refresh()->status);
        $this->assertNull($listing->buyer_account_id);
    }

    /**
     * Confirms a warehouse outage preserves payment and retries delivery safely.
     */
    public function test_failed_delivery_remains_pending_and_scheduler_retries_it(): void
    {
        $listing = MarketListing::factory()->create([
            'seller_account_id' => 'seller04',
            'price_credits' => 75,
        ]);
        AccountWallet::factory()->create([
            'account_id' => 'buyer004',
            'balance' => 100,
        ]);
        $this->mockExistingAccounts('buyer004', 'seller04');
        $escrow = $this->mock(MarketItemEscrowRepository::class);
        $escrow->shouldReceive('deposit')
            ->once()
            ->andThrow(new RuntimeException('Sensitive SQL Server detail'));

        $purchased = $this->purchase(
            buyerAccountId: 'buyer004',
            listingId: (int) $listing->getKey(),
            idempotencyKey: '1821eab0-aa1a-47b6-a8b5-e16dcb62d0a2',
        );

        $this->assertSame(MarketListingStatus::DeliveryPending, $purchased->status);
        $this->assertSame('game_sync_failed', $purchased->last_sync_error);
        $this->assertSame(25, $this->balance('buyer004'));
        $this->assertSame(75, $this->balance('seller04'));

        $escrow->shouldReceive('deposit')
            ->once()
            ->andReturn(MarketEscrowResult::Deposited);
        $this->artisan('market:sync-escrow')
            ->expectsOutputToContain('1 synced, 0 pending retry')
            ->assertSuccessful();

        $this->assertSame(MarketListingStatus::Delivered, $purchased->refresh()->status);
    }

    /**
     * Confirms sellers cannot manufacture Credits by buying their own listing.
     */
    public function test_seller_cannot_purchase_an_owned_listing(): void
    {
        $listing = MarketListing::factory()->create([
            'seller_account_id' => 'seller05',
        ]);
        $this->mock(MarketItemEscrowRepository::class)->shouldNotReceive('deposit');

        try {
            $this->purchase(
                buyerAccountId: 'seller05',
                listingId: (int) $listing->getKey(),
                idempotencyKey: '9ef58582-6e12-4599-a638-0f86ef09f5a8',
            );
            $this->fail('A seller must not purchase an owned listing.');
        } catch (MarketListingRejected $exception) {
            $this->assertSame(MarketListingBlockReason::OwnListing, $exception->reason);
        }

        $this->assertSame(MarketListingStatus::Active, $listing->refresh()->status);
        $this->assertSame(0, WalletTransaction::query()->count());
    }

    /**
     * Confirms one replay key cannot purchase a different listing or charge twice.
     */
    public function test_replay_key_reuse_for_another_listing_is_rejected(): void
    {
        $firstListing = MarketListing::factory()->create([
            'seller_account_id' => 'seller06',
            'price_credits' => 50,
        ]);
        $secondListing = MarketListing::factory()->create([
            'seller_account_id' => 'seller07',
            'price_credits' => 60,
        ]);
        AccountWallet::factory()->create([
            'account_id' => 'buyer006',
            'balance' => 200,
        ]);
        $this->mockExistingAccounts('buyer006', 'seller06');
        $this->mock(MarketItemEscrowRepository::class)
            ->shouldReceive('deposit')
            ->once()
            ->andReturn(MarketEscrowResult::Deposited);
        $key = 'a2e67686-df9c-4f4b-aa09-9fe68429bd3f';

        $this->purchase('buyer006', (int) $firstListing->getKey(), $key);

        try {
            $this->purchase('buyer006', (int) $secondListing->getKey(), $key);
            $this->fail('One market replay key must not authorize a second listing.');
        } catch (MarketListingRejected $exception) {
            $this->assertSame(MarketListingBlockReason::IdempotencyConflict, $exception->reason);
        }

        $this->assertSame(150, $this->balance('buyer006'));
        $this->assertSame(MarketListingStatus::Active, $secondListing->refresh()->status);
        $this->assertSame(2, WalletTransaction::query()->count());
    }

    /**
     * Confirms the authenticated HTTP endpoint executes the same purchase boundary.
     */
    public function test_authenticated_account_can_purchase_through_the_market_route(): void
    {
        $listing = MarketListing::factory()->create([
            'seller_account_id' => 'seller08',
            'price_credits' => 80,
        ]);
        AccountWallet::factory()->create([
            'account_id' => 'buyer008',
            'balance' => 100,
        ]);
        $this->mockExistingAccounts('buyer008', 'seller08');
        $this->mock(MarketItemEscrowRepository::class)
            ->shouldReceive('deposit')
            ->once()
            ->andReturn(MarketEscrowResult::Deposited);
        $csrfToken = 'market-purchase-endpoint-token';

        $response = $this
            ->actingAs($this->account('buyer008'))
            ->withSession(['_token' => $csrfToken])
            ->from('/market')
            ->post(route('market.listings.purchase', $listing), [
                '_token' => $csrfToken,
                'idempotency_key' => '86de2e26-834f-4e11-99ec-e6aa9493c6fd',
            ]);

        $response
            ->assertRedirect('/market')
            ->assertSessionHas('marketStatus');
        $this->assertSame(MarketListingStatus::Delivered, $listing->refresh()->status);
        $this->assertSame(20, $this->balance('buyer008'));
    }

    /**
     * Executes the market purchase action from the service container.
     */
    private function purchase(
        string $buyerAccountId,
        int $listingId,
        string $idempotencyKey,
    ): MarketListing {
        return $this->app->make(PurchaseMarketListing::class)->execute(
            buyerAccountId: $buyerAccountId,
            listingId: $listingId,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * Returns one Credits balance by account identifier.
     */
    private function balance(string $accountId): int
    {
        return (int) AccountWallet::query()
            ->where('account_id', $accountId)
            ->where('currency', WalletCurrency::Credits)
            ->value('balance');
    }

    /**
     * Makes wallet account-existence checks succeed only for expected identifiers.
     */
    private function mockExistingAccounts(string ...$accountIds): void
    {
        $lookup = $this->mock(GameAccountLookupRepository::class);
        $lookup->shouldReceive('exists')
            ->times(count($accountIds))
            ->andReturnUsing(
                static fn (string $accountId): bool => in_array($accountId, $accountIds, true),
            );
    }

    /**
     * Builds one authenticated legacy game account for route checks.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: $username,
            displayName: 'Market Buyer',
            email: null,
            blocked: false,
            vip: false,
            password: 'secret1',
        );
    }
}
