<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Game\Contracts\MarketItemEscrowRepository;
use App\Market\MarketEscrowResult;
use App\Market\MarketListingStatus;
use App\Models\Administrator;
use App\Models\MarketAdminAction;
use App\Models\MarketListing;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies permission-scoped and audited market escrow recovery operations.
 */
final class AdminMarketTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Enables mocked game writes for recovery action tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);
    }

    /**
     * Confirms market operations require the granular economy capability.
     */
    public function test_market_operations_require_economy_permission(): void
    {
        $economyManager = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $moderator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ModerateAccounts])
            ->create();

        $this->actingAs($economyManager, 'admin')
            ->get('/admin/economy/market')
            ->assertOk()
            ->assertSee('Market operations');
        $this->actingAs($moderator, 'admin')
            ->get('/admin/economy/market')
            ->assertForbidden();
    }

    /**
     * Confirms an economy manager can retry delivery with an immutable audit row.
     */
    public function test_economy_manager_retries_pending_buyer_delivery(): void
    {
        $administrator = $this->economyManager();
        $listing = MarketListing::factory()->create([
            'buyer_account_id' => 'buyeradmin',
            'status' => MarketListingStatus::DeliveryPending,
            'purchase_idempotency_key' => 'b12cd9af-a836-48fa-a215-4690c20e1342',
            'purchase_request_hash' => hash('sha256', 'admin-market'),
            'purchased_at' => now(),
        ]);
        $this->mock(MarketItemEscrowRepository::class)
            ->shouldReceive('deposit')
            ->once()
            ->with('buyeradmin', $listing->item_binary, 1, 2)
            ->andReturn(MarketEscrowResult::Deposited);
        $csrfToken = 'admin-market-retry-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post('/admin/economy/market/'.$listing->public_id.'/retry', [
                '_token' => $csrfToken,
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(MarketListingStatus::Delivered, $listing->refresh()->status);
        $this->assertDatabaseHas('market_admin_actions', [
            'market_listing_id' => $listing->getKey(),
            'administrator_id' => $administrator->getKey(),
            'action' => 'retry_sync',
            'before_status' => MarketListingStatus::DeliveryPending->value,
            'after_status' => MarketListingStatus::Delivered->value,
            'outcome' => 'completed',
        ]);
    }

    /**
     * Confirms an active unsold item can be returned without touching sold listings.
     */
    public function test_economy_manager_forces_unsold_listing_return(): void
    {
        $administrator = $this->economyManager();
        $listing = MarketListing::factory()->create([
            'seller_account_id' => 'selleradm',
            'status' => MarketListingStatus::Active,
        ]);
        $this->mock(MarketItemEscrowRepository::class)
            ->shouldReceive('deposit')
            ->once()
            ->with('selleradm', $listing->item_binary, 1, 2)
            ->andReturn(MarketEscrowResult::Deposited);
        $csrfToken = 'admin-market-return-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post('/admin/economy/market/'.$listing->public_id.'/return', [
                '_token' => $csrfToken,
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame(MarketListingStatus::Returned, $listing->refresh()->status);
        $action = MarketAdminAction::query()->sole();
        $this->assertSame('force_return', $action->action);
        $this->assertSame('completed', $action->outcome);
        $this->assertSame($listing->public_id, $action->context_snapshot['public_id']);
    }

    /**
     * Creates a Game Master with only the market's required economy permission.
     */
    private function economyManager(): Administrator
    {
        return Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
    }
}
