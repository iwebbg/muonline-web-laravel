<?php

namespace Tests\Feature;

use App\Game\Events\EventDefinition;
use App\Game\Events\EventProfileReader;
use App\Game\Events\EventSchedule;
use App\Models\Administrator;
use App\Models\EventScheduleEntry;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies calendar recurrences without rolling missing monthly dates into another day.
 */
final class MonthlyEventScheduleTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Finds the next valid 31st across February and retains the previous actual occurrence.
     */
    public function test_missing_calendar_day_is_skipped_without_losing_the_next_event(): void
    {
        $definition = EventDefinition::fromConfiguration([
            'key' => 'monthly-final', 'name' => 'Monthly Final', 'times' => ['22:00'],
            'days_of_month' => [31], 'duration_minutes' => 0,
        ]);
        $events = (new EventSchedule)->upcoming([$definition], new DateTimeImmutable('2026-02-01T12:00:00+02:00'));

        self::assertCount(1, $events);
        self::assertSame('2026-01-31 22:00', $events[0]->previousOccurrence->format('Y-m-d H:i'));
        self::assertSame('2026-03-31 22:00', $events[0]->nextOccurrence()->format('Y-m-d H:i'));
        self::assertSame([31], $events[0]->toClientArray()['daysOfMonth']);
    }

    /**
     * Applies weekday and calendar restrictions together across a year boundary.
     */
    public function test_monthly_weekday_intersection_preserves_the_next_valid_calendar_date(): void
    {
        $definition = EventDefinition::fromConfiguration([
            'key' => 'monthly-weekend', 'name' => 'Weekend Final', 'times' => ['10:00'],
            'days_of_month' => [1, 2, 3, 4, 5, 6, 7], 'days_of_week' => [7],
        ]);
        $events = (new EventSchedule)->upcoming([$definition], new DateTimeImmutable('2026-12-30T12:00:00+02:00'));

        self::assertSame('2026-12-06 10:00', $events[0]->previousOccurrence->format('Y-m-d H:i'));
        self::assertSame('2027-01-03 10:00', $events[0]->nextOccurrence()->format('Y-m-d H:i'));
    }

    /**
     * Retains infrequent weekday intersections whose previous occurrence was months ago.
     */
    public function test_sparse_calendar_intersection_searches_beyond_two_months(): void
    {
        $definition = EventDefinition::fromConfiguration([
            'key' => 'sunday-final', 'name' => 'Sunday Final', 'times' => ['10:00'],
            'days_of_month' => [31], 'days_of_week' => [7],
        ]);
        $events = (new EventSchedule)->upcoming([$definition], new DateTimeImmutable('2026-04-01T12:00:00+02:00'));

        self::assertCount(1, $events);
        self::assertSame('2025-08-31 10:00', $events[0]->previousOccurrence->format('Y-m-d H:i'));
        self::assertSame('2026-05-31 10:00', $events[0]->nextOccurrence()->format('Y-m-d H:i'));
    }

    /**
     * Keeps the configured local opening hour when the next month crosses daylight saving time.
     */
    public function test_monthly_event_keeps_local_time_across_the_sofia_clock_change(): void
    {
        $definition = EventDefinition::fromConfiguration([
            'key' => 'monthly-final', 'name' => 'Monthly Final', 'times' => ['22:00'], 'days_of_month' => [31],
        ]);
        $events = (new EventSchedule)->upcoming([$definition], new DateTimeImmutable('2026-03-01 12:00:00', new \DateTimeZone('Europe/Sofia')));

        self::assertSame('2026-01-31T22:00:00+02:00', $events[0]->previousOccurrence->format(DATE_ATOM));
        self::assertSame('2026-03-31T22:00:00+03:00', $events[0]->nextOccurrence()->format(DATE_ATOM));
    }

    /**
     * Saves and audits a monthly override through the existing administration endpoint.
     */
    public function test_administrator_can_persist_calendar_restrictions_and_read_them_back(): void
    {
        $token = 'monthly-event-token';
        $this->actingAs(Administrator::factory()->create(), 'admin')->withSession(['_token' => $token])
            ->post(route('admin.event-schedules.store'), [
                '_token' => $token, 'event_key' => 'monthly-final', 'name_en' => 'Monthly Final',
                'location_en' => 'Tormented Square', 'accent' => 'violet', 'enabled' => true,
                'duration_minutes' => 0, 'schedule_mode' => 'times', 'times' => '22:00',
                'days_of_month' => [23, 18], 'sort_order' => 1,
            ])->assertRedirect()->assertSessionHasNoErrors();
        $entry = EventScheduleEntry::query()->sole();
        $definitions = $this->app->make(EventProfileReader::class)->definitions();
        $definition = array_values(array_filter($definitions, static fn (EventDefinition $definition): bool => $definition->key === 'monthly-final'))[0];

        self::assertSame([18, 23], $entry->days_of_month);
        self::assertSame([18, 23], $definition->daysOfMonth);
        $this->assertDatabaseCount('event_schedule_entry_revisions', 1);
    }
}
