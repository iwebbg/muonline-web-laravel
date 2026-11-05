<?php

namespace Tests\Feature;

use App\AccountRegistration\AccountRegistrationStatus;
use App\Auth\GameAccount;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\AccountRegistrationOperation;
use App\Models\Administrator;
use App\Models\CharacterResetOperation;
use App\Models\EconomyRecoveryAction;
use App\Models\EventScheduleEntry;
use App\Models\EventScheduleEntryRevision;
use App\Models\ItemGrantOperation;
use App\Models\MarketAdminAction;
use App\Models\ModuleProfile;
use App\Models\ModuleProfileRevision;
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
     * Confirms inclusive date filters exclude audit events outside the selected range.
     */
    public function test_administrator_can_filter_audit_events_by_date_range(): void
    {
        $administrator = Administrator::factory()->create();
        CharacterResetOperation::factory()->create([
            'account_id' => 'inside-range',
            'status' => CharacterOperationStatus::Completed,
            'created_at' => '2026-11-02 12:00:00',
            'updated_at' => '2026-11-02 12:00:00',
        ]);
        CharacterResetOperation::factory()->create([
            'account_id' => 'outside-range',
            'status' => CharacterOperationStatus::Completed,
            'created_at' => '2026-10-31 23:59:59',
            'updated_at' => '2026-10-31 23:59:59',
        ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/audit-log?type=character_reset&date_from=2026-11-01&date_to=2026-11-03');

        $response
            ->assertOk()
            ->assertSee('inside-range')
            ->assertDontSee('outside-range');
    }

    /**
     * Confirms normalized audit history is paginated and retains active filters.
     */
    public function test_audit_log_paginates_twenty_events_and_preserves_filters(): void
    {
        $administrator = Administrator::factory()->create();

        for ($eventNumber = 1; $eventNumber <= 21; $eventNumber++) {
            $accountSuffix = str_pad((string) $eventNumber, 2, '0', STR_PAD_LEFT);
            CharacterResetOperation::factory()->create([
                'account_id' => 'audit-account-'.$accountSuffix,
                'status' => CharacterOperationStatus::Completed,
                'created_at' => '2026-11-02 12:'.str_pad((string) $eventNumber, 2, '0', STR_PAD_LEFT).':00',
                'updated_at' => '2026-11-02 12:'.str_pad((string) $eventNumber, 2, '0', STR_PAD_LEFT).':00',
            ]);
        }

        $firstPage = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/audit-log?type=character_reset&date_from=2026-11-01&date_to=2026-11-03');
        $secondPage = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/audit-log?type=character_reset&date_from=2026-11-01&date_to=2026-11-03&page=2');

        $firstPage
            ->assertOk()
            ->assertSee('audit-account-21')
            ->assertDontSee('audit-account-01')
            ->assertSee('date_from=2026-11-01', false)
            ->assertSee('page=2', false);
        $secondPage
            ->assertOk()
            ->assertSee('audit-account-01')
            ->assertDontSee('audit-account-21');
    }

    /**
     * Confirms a reversed audit date range is rejected before querying history.
     */
    public function test_audit_log_rejects_reversed_date_range(): void
    {
        $administrator = Administrator::factory()->create();

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/audit-log?date_from=2026-11-03&date_to=2026-11-01');

        $response->assertSessionHasErrors('date_to');
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

    /**
     * Confirms module availability revisions are visible with complete before and after snapshots.
     */
    public function test_administrator_can_filter_module_profile_revisions(): void
    {
        $administrator = Administrator::factory()->create();
        $profile = ModuleProfile::query()->create([
            'profile_key' => 'audit-profile',
            'game_version' => '97d+99i',
            'modules' => [
                'market' => true,
            ],
            'revision' => 2,
            'updated_by_administrator_id' => $administrator->getKey(),
        ]);

        ModuleProfileRevision::query()->create([
            'module_profile_id' => $profile->getKey(),
            'revision' => 2,
            'before_snapshot' => [
                'market' => false,
            ],
            'after_snapshot' => [
                'market' => true,
            ],
            'changed_by_administrator_id' => $administrator->getKey(),
        ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/audit-log?type=module_profile');

        $response
            ->assertOk()
            ->assertSee('Module profile')
            ->assertSee('module-profile:'.$profile->getKey())
            ->assertSee('market')
            ->assertSee('false')
            ->assertSee('true');
    }

    /**
     * Confirms custom event schedule revisions expose their action and bilingual snapshot safely.
     */
    public function test_administrator_can_filter_event_schedule_revisions(): void
    {
        $administrator = Administrator::factory()->create();
        $event = EventScheduleEntry::query()->create([
            'profile_key' => 'audit-profile',
            'game_version' => '97d+99i',
            'event_key' => 'audit-invasion',
            'name_en' => 'Audit Invasion',
            'name_bg' => 'Одит инвазия',
            'location_en' => 'Lorencia',
            'location_bg' => 'Лоренсия',
            'accent' => 'crimson',
            'enabled' => true,
            'duration_minutes' => 15,
            'schedule_mode' => 'times',
            'times' => ['12:00'],
            'days_of_week' => [],
            'interval_minutes' => null,
            'interval_anchor' => '00:00',
            'source' => 'audit-test',
            'sort_order' => 1,
            'revision' => 1,
            'updated_by_administrator_id' => $administrator->getKey(),
        ]);

        EventScheduleEntryRevision::query()->create([
            'event_schedule_entry_id' => $event->getKey(),
            'revision' => 1,
            'action' => 'created',
            'before_snapshot' => null,
            'after_snapshot' => [
                'name_en' => 'Audit Invasion',
                'times' => ['12:00'],
            ],
            'changed_by_administrator_id' => $administrator->getKey(),
        ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/audit-log?type=event_schedule');

        $response
            ->assertOk()
            ->assertSee('Event schedule')
            ->assertSee('event-schedule:'.$event->getKey())
            ->assertSee('created')
            ->assertSee('Audit Invasion')
            ->assertSee('12:00');
    }

    /**
     * Confirms item grants expose custody outcomes without leaking exact legacy item bytes.
     */
    public function test_administrator_can_filter_item_grant_operations_without_raw_bytes(): void
    {
        $administrator = Administrator::factory()->create();
        $operation = ItemGrantOperation::factory()->create([
            'administrator_id' => $administrator->getKey(),
            'account_id' => 'grantowner',
            'item_name' => 'Kris',
            'item_binary' => hex2bin('0000FF00112233445566'),
            'context_snapshot' => [
                'item_name' => 'Kris',
                'warehouse_vault' => 1,
            ],
        ]);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/audit-log?type=item_grant');

        $response
            ->assertOk()
            ->assertSee('Item grant')
            ->assertSee('grantowner')
            ->assertSee('Kris')
            ->assertSee('warehouse_vault')
            ->assertDontSee(strtoupper(bin2hex($operation->item_binary)));
    }
}
