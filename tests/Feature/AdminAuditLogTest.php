<?php

namespace Tests\Feature;

use App\AccountRegistration\AccountRegistrationStatus;
use App\Auth\GameAccount;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\AccountRegistrationOperation;
use App\Models\Administrator;
use App\Models\CharacterResetOperation;
use App\Models\MarketAdminAction;
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
}
