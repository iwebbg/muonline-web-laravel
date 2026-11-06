<?php

namespace Tests\Feature;

use App\Admin\Audit\AuditLogReader;
use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use App\Models\EventScheduleEntry;
use App\Models\EventScheduleEntryRevision;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies permission-scoped and audited event schedule administration.
 */
final class AdminEventScheduleTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms staff without settings permission cannot access event schedules.
     */
    public function test_game_master_without_settings_permission_is_forbidden(): void
    {
        $gameMaster = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageContent])
            ->create();

        $response = $this
            ->actingAs($gameMaster, 'admin')
            ->get(route('admin.event-schedules.index'));

        $response->assertForbidden();
    }

    /**
     * Confirms the event schedule workspace renders before any override is saved.
     */
    public function test_administrator_can_view_event_schedules_without_persisted_overrides(): void
    {
        $administrator = Administrator::factory()->create();

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get(route('admin.event-schedules.index'));

        $response->assertOk();
    }

    /**
     * Confirms a valid explicit-time event is normalized and audited.
     */
    public function test_valid_event_is_created_with_normalized_schedule_and_audit(): void
    {
        $administrator = Administrator::factory()->create();
        $csrfToken = 'create-event-schedule-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post(route('admin.event-schedules.store'), [
                '_token' => $csrfToken,
                ...$this->validPayload(),
                'times' => '20:30, 10:00 20:30',
                'days_of_week' => ['5', '1'],
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHas('status');

        $entry = EventScheduleEntry::query()->sole();
        $revision = EventScheduleEntryRevision::query()->sole();

        $this->assertSame(['10:00', '20:30'], $entry->times);
        $this->assertSame([1, 5], $entry->days_of_week);
        $this->assertSame('darksteam_97d99i', $entry->profile_key);
        $this->assertSame(1, $entry->revision);
        $this->assertNull($revision->before_snapshot);
        $this->assertSame('created', $revision->action);
        $this->assertSame('custom-arena', $revision->after_snapshot['event_key']);

        $this->actingAs($administrator, 'admin')
            ->get(route('admin.event-schedules.index'))
            ->assertOk()
            ->assertSee('custom-arena · Created')
            ->assertSee('View')
            ->assertSee('Changed settings')
            ->assertSee('English name')
            ->assertSee('Custom Arena')
            ->assertSee('x-teleport="body"', false)
            ->assertSee('aria-modal="true"', false);

        $auditEntries = $this->app->make(AuditLogReader::class)->latest(type: 'event_schedule');

        $this->assertCount(1, $auditEntries);
        $this->assertSame('event_schedule', $auditEntries[0]['type']);
    }

    /**
     * Confirms invalid event times return validation errors without persistence.
     */
    public function test_invalid_time_is_rejected_without_audit_write(): void
    {
        $administrator = Administrator::factory()->create();
        $csrfToken = 'invalid-event-schedule-token';

        $response = $this
            ->from(route('admin.event-schedules.index'))
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post(route('admin.event-schedules.store'), [
                '_token' => $csrfToken,
                ...$this->validPayload(),
                'times' => '25:99',
            ]);

        $response
            ->assertRedirect(route('admin.event-schedules.index'))
            ->assertSessionHasErrors('times');
        $this->assertSame(0, EventScheduleEntry::query()->count());
        $this->assertSame(0, EventScheduleEntryRevision::query()->count());
    }

    /**
     * Confirms an entry from another realm profile cannot be updated by URL tampering.
     */
    public function test_cross_profile_event_update_returns_not_found(): void
    {
        $administrator = Administrator::factory()->create();
        $entry = EventScheduleEntry::query()->create([
            ...$this->entryAttributes(),
            'profile_key' => 'another-profile',
            'game_version' => 'Season X',
        ]);
        $csrfToken = 'cross-profile-event-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->put(route('admin.event-schedules.update', $entry), [
                '_token' => $csrfToken,
                ...$this->validPayload(),
            ]);

        $response->assertNotFound();
        $this->assertSame('Original Event', $entry->refresh()->name_en);
        $this->assertSame(0, EventScheduleEntryRevision::query()->count());
    }

    /**
     * Returns one valid event form payload.
     *
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'event_key' => 'custom-arena',
            'name_en' => 'Custom Arena',
            'name_bg' => 'Къстъм арена',
            'location_en' => 'Arena',
            'location_bg' => 'Арена',
            'accent' => 'violet',
            'enabled' => '1',
            'duration_minutes' => 15,
            'schedule_mode' => 'times',
            'times' => '10:00',
            'days_of_week' => [],
            'source' => 'Operator-defined event',
            'sort_order' => 50,
        ];
    }

    /**
     * Returns persistence-ready attributes for an existing foreign profile event.
     *
     * @return array<string, mixed>
     */
    private function entryAttributes(): array
    {
        return [
            'event_key' => 'foreign-event',
            'name_en' => 'Original Event',
            'name_bg' => null,
            'location_en' => 'Arena',
            'location_bg' => null,
            'accent' => 'crimson',
            'enabled' => true,
            'duration_minutes' => 10,
            'schedule_mode' => 'times',
            'times' => ['10:00'],
            'days_of_week' => [],
            'interval_minutes' => null,
            'interval_anchor' => '00:00',
            'source' => null,
            'sort_order' => 0,
            'revision' => 1,
        ];
    }
}
