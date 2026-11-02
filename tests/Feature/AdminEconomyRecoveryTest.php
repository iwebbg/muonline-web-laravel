<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Game\Contracts\ItemEscrowRepository;
use App\Game\Contracts\ZenBankRepository;
use App\Market\MarketEscrowResult;
use App\Models\AccountZenBalance;
use App\Models\Administrator;
use App\Models\EconomyRecoveryAction;
use App\Models\WebStorageItem;
use App\Models\ZenBankOperation;
use App\WebStorage\WebStorageItemStatus;
use App\ZenBank\ZenBankOperationStatus;
use App\ZenBank\ZenBankOperationType;
use App\ZenBank\ZenProjectionResult;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies permission-scoped and audited administrator economy recovery.
 */
final class AdminEconomyRecoveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Enables mocked game writes for explicit recovery attempts.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);
    }

    /**
     * Confirms Game Masters without economy permission cannot inspect recovery state.
     */
    public function test_recovery_requires_manage_economy_permission(): void
    {
        $gameMaster = Administrator::factory()->gameMaster()->create();

        $this->actingAs($gameMaster, 'admin')
            ->get(route('admin.economy-recovery.index'))
            ->assertForbidden();
    }

    /**
     * Confirms authorized staff can inspect pending item and Zen operations.
     */
    public function test_authorized_game_master_can_view_recovery_queue(): void
    {
        $gameMaster = $this->economyGameMaster();
        WebStorageItem::factory()->create([
            'account_id' => 'storage01',
            'status' => WebStorageItemStatus::Storing,
        ]);
        ZenBankOperation::factory()->create([
            'account_id' => 'zenowner',
            'status' => ZenBankOperationStatus::Conflict,
            'last_sync_error' => 'game_balance_conflict',
        ]);

        $response = $this
            ->actingAs($gameMaster, 'admin')
            ->get(route('admin.economy-recovery.index'));

        $response
            ->assertOk()
            ->assertSee('Economy recovery')
            ->assertSee('storage01')
            ->assertSee('zenowner')
            ->assertSee('game_balance_conflict');
    }

    /**
     * Confirms an item retry uses the exact-byte synchronizer and records the administrator.
     */
    public function test_item_custody_retry_is_audited(): void
    {
        $gameMaster = $this->economyGameMaster();
        $item = WebStorageItem::factory()->create([
            'account_id' => 'storage02',
            'status' => WebStorageItemStatus::Storing,
            'stored_at' => null,
            'sync_attempts' => 1,
        ]);
        $this->mock(ItemEscrowRepository::class)
            ->shouldReceive('extract')
            ->once()
            ->andReturn(MarketEscrowResult::AlreadyExtracted);
        $csrfToken = 'item-custody-recovery-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($gameMaster, 'admin')
            ->post(route('admin.economy-recovery.storage.retry', $item), [
                '_token' => $csrfToken,
            ]);

        $response->assertRedirect();
        $this->assertSame(WebStorageItemStatus::Available, $item->refresh()->status);
        $this->assertDatabaseHas('economy_recovery_actions', [
            'administrator_id' => $gameMaster->getKey(),
            'subject_type' => 'item_custody',
            'account_id' => 'storage02',
            'before_status' => 'storing',
            'after_status' => 'available',
            'outcome' => 'completed',
        ]);
    }

    /**
     * Confirms a Zen retry finalizes its ledger and records the administrator.
     */
    public function test_zen_retry_is_audited(): void
    {
        $gameMaster = $this->economyGameMaster();
        $balance = AccountZenBalance::factory()->create([
            'account_id' => 'zenretry',
            'balance' => 0,
        ]);
        $operation = ZenBankOperation::factory()->create([
            'account_zen_balance_id' => $balance->getKey(),
            'account_id' => 'zenretry',
            'type' => ZenBankOperationType::Deposit,
            'status' => ZenBankOperationStatus::Pending,
            'before_bank_balance' => 0,
            'after_bank_balance' => 1_000_000,
            'completed_at' => null,
        ]);
        $this->mock(ZenBankRepository::class)
            ->shouldReceive('project')
            ->once()
            ->andReturn(ZenProjectionResult::AlreadyApplied);
        $csrfToken = 'zen-bank-recovery-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($gameMaster, 'admin')
            ->post(route('admin.economy-recovery.zen.retry', $operation), [
                '_token' => $csrfToken,
            ]);

        $response->assertRedirect();
        $this->assertSame(ZenBankOperationStatus::Completed, $operation->refresh()->status);
        $this->assertSame(1_000_000, $balance->refresh()->balance);
        $this->assertSame(1, EconomyRecoveryAction::query()->count());
        $this->assertDatabaseHas('economy_recovery_actions', [
            'administrator_id' => $gameMaster->getKey(),
            'subject_type' => 'zen_bank',
            'account_id' => 'zenretry',
            'before_status' => 'pending',
            'after_status' => 'completed',
            'outcome' => 'completed',
        ]);
    }

    /**
     * Creates one Game Master with only the required economy capability.
     */
    private function economyGameMaster(): Administrator
    {
        return Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
    }
}
