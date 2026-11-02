<?php

namespace Tests\Feature;

use App\AccountRegistration\AccountRegistrationStatus;
use App\Auth\GameAccount;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\AccountRegistrationOperation;
use App\Models\Administrator;
use App\Models\CharacterResetOperation;
use App\Models\EconomyRecoveryAction;
use App\Models\MarketAdminAction;
use App\Models\WebStorageItem;
use App\Models\ZenBankOperation;
use App\WebStorage\WebStorageItemStatus;
use App\ZenBank\ZenBankOperationStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the protected and normalized administrator audit log.
 */
final class AdminAuditLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms guests cannot inspect application-owned operation history.
     */
    public function test_guest_is_redirected_from_audit_log(): void
    {
        $response = $this->get('/admin/audit-log');

        $response->assertRedirect('/admin/login');
    }

    /**
     * Confirms a player session never grants access to privileged audit data.
     */
    public function test_game_account_cannot_access_audit_log(): void
    {
        $gameAccount = new GameAccount(
            guid: 1,
            username: 'player',
            displayName: 'Player',
            email: null,
            blocked: false,
            vip: false,
            password: 'secret1',
        );

        $response = $this
            ->actingAs($gameAccount)
            ->get('/admin/audit-log');

        $response->assertRedirect('/admin/login');
    }

    /**
     * Confirms administrators see normalized events while unsafe subjects remain escaped.
     */
    public function test_administrator_can_inspect_escaped_audit_events(): void
    {
        $dangerousAccountId = '<script>alert(1)</script>';
        $administrator = Administrator::factory()->create();

        CharacterResetOperation::factory()->create([
            'account_id' => $dangerousAccountId,
            'character_name' => 'AuditHero',
            'status' => CharacterOperationStatus::Completed,
            'before_snapshot' => [
                'level' => 400,
            ],
            'after_snapshot' => [
                'level' => 1,
            ],
            'completed_at' => now(),
        ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/audit-log');

        $response->assertOk();
        $response->assertSee('Immutable audit trail');
        $response->assertSee('Character reset');
        $response->assertSee('AuditHero');
        $response->assertSeeText($dangerousAccountId);
        $response->assertDontSee($dangerousAccountId, false);
        $response->assertSee('400');
    }

    /**
     * Confirms allow-listed filters return only matching normalized operations.
     */
    public function test_administrator_can_filter_audit_events(): void
    {
        $administrator = Administrator::factory()->create();

        CharacterResetOperation::factory()->create([
            'account_id' => 'auditowner',
            'character_name' => 'FailedHero',
            'status' => CharacterOperationStatus::Failed,
            'failure_code' => 'character_online',
        ]);
        AccountRegistrationOperation::factory()->create([
            'account_id' => 'newaccount',
            'status' => AccountRegistrationStatus::Completed,
        ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/audit-log?type=character_reset&status=failed&search=FailedHero');

        $response->assertOk();
        $response->assertSee('FailedHero');
        $response->assertSee('character_online');
        $response->assertDontSee('newaccount');
    }

    /**
     * Confirms market recovery actions join the normalized immutable audit trail.
     */
    public function test_administrator_can_filter_market_recovery_events(): void
    {
        $administrator = Administrator::factory()->create();
        MarketAdminAction::factory()->create([
            'administrator_id' => $administrator->getKey(),
            'action' => 'force_return',
            'before_status' => 'active',
            'after_status' => 'returned',
            'outcome' => 'completed',
            'context_snapshot' => [
                'seller_account_id' => 'marketseller',
            ],
        ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/audit-log?type=market_recovery');

        $response
            ->assertOk()
            ->assertSee('Market recovery')
            ->assertSee('marketseller')
            ->assertSee('force_return')
            ->assertSee('returned');
    }

    /**
     * Confirms exact-item custody entries expose fingerprints and lifecycle failures.
     */
    public function test_administrator_can_filter_item_custody_events(): void
    {
        $administrator = Administrator::factory()->create();
        WebStorageItem::factory()->create([
            'account_id' => 'vaultowner',
            'source_character_name' => 'VaultHero',
            'status' => WebStorageItemStatus::Failed,
            'last_sync_error' => 'item_changed',
            'escrow_key' => null,
        ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/audit-log?type=item_custody&status=failed');

        $response
            ->assertOk()
            ->assertSee('Item custody')
            ->assertSee('vaultowner')
            ->assertSee('VaultHero')
            ->assertSee('item_changed');
    }

    /**
     * Confirms conflicted Zen transfers expose their exact before and after ledger values.
     */
    public function test_administrator_can_filter_zen_bank_events(): void
    {
        $administrator = Administrator::factory()->create();
        ZenBankOperation::factory()->create([
            'account_id' => 'zenowner',
            'character_name' => 'ZenHero',
            'status' => ZenBankOperationStatus::Conflict,
            'last_sync_error' => 'game_balance_conflict',
            'before_game_zen' => 20_000_000,
            'after_game_zen' => 15_000_000,
        ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/audit-log?type=zen_bank&status=failed');

        $response
            ->assertOk()
            ->assertSee('Zen Bank')
            ->assertSee('zenowner')
            ->assertSee('ZenHero')
            ->assertSee('game_balance_conflict')
            ->assertSee('20000000')
            ->assertSee('15000000');
    }

    /**
     * Confirms administrator economy recovery attempts join the immutable audit trail.
     */
    public function test_administrator_can_filter_economy_recovery_actions(): void
    {
        $administrator = Administrator::factory()->create();
        EconomyRecoveryAction::factory()->create([
            'administrator_id' => $administrator->getKey(),
            'account_id' => 'recoveryowner',
            'before_status' => 'pending',
            'after_status' => 'completed',
        ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/audit-log?type=economy_recovery');

        $response
            ->assertOk()
            ->assertSee('Economy recovery')
            ->assertSee('recoveryowner')
            ->assertSee('retry_sync')
            ->assertSee('completed');
    }
}
