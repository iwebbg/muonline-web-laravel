<?php

namespace Tests\Unit;

use App\Game\Events\EventDefinition;
use App\Game\Events\EventSchedule;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Verifies deterministic recurring event calculations in server local time.
 */
final class EventScheduleTest extends TestCase
{
    /**
     * Confirms that the schedule returns the surrounding and future occurrences.
     */
    public function test_schedule_calculates_previous_and_future_occurrences(): void
    {
        $definitions = [
            $this->definition(
                key: 'blood-castle',
                times: ['18:00', '10:00', '12:00'],
            ),
        ];
        $now = new DateTimeImmutable('2026-11-02T10:20:00+02:00');

        $events = (new EventSchedule)->upcoming($definitions, $now, 1);

        $this->assertCount(1, $events);
        $this->assertSame('2026-11-02T10:00:00+02:00', $events[0]->previousOccurrence->format(DATE_ATOM));
        $this->assertSame('2026-11-02T12:00:00+02:00', $events[0]->occurrences[0]->format(DATE_ATOM));
        $this->assertSame('2026-11-02T18:00:00+02:00', $events[0]->occurrences[1]->format(DATE_ATOM));
        $this->assertSame(['10:00', '12:00', '18:00'], $events[0]->dailyTimes);
    }

    /**
     * Confirms that a schedule wraps from the last event today to tomorrow.
     */
    public function test_schedule_wraps_to_next_server_day(): void
    {
        $definitions = [
            $this->definition(
                key: 'golden-invasion',
                times: ['00:45', '23:45'],
            ),
        ];
        $now = new DateTimeImmutable('2026-11-02T23:50:00+02:00');

        $events = (new EventSchedule)->upcoming($definitions, $now, 1);

        $this->assertSame('2026-11-02T23:45:00+02:00', $events[0]->previousOccurrence->format(DATE_ATOM));
        $this->assertSame('2026-11-03T00:45:00+02:00', $events[0]->occurrences[0]->format(DATE_ATOM));
    }

    /**
     * Confirms that malformed or duplicate time values cannot corrupt the client schedule.
     */
    public function test_schedule_filters_invalid_and_duplicate_times(): void
    {
        $definitions = [
            $this->definition(
                key: 'devil-square',
                times: ['10:30', 'invalid', '25:00', '10:30', '12:30'],
            ),
            $this->definition(
                key: 'invalid-event',
                times: ['not-a-time'],
            ),
        ];
        $now = new DateTimeImmutable('2026-11-02T11:00:00+02:00');

        $events = (new EventSchedule)->upcoming($definitions, $now);

        $this->assertCount(1, $events);
        $this->assertSame(['10:30', '12:30'], $events[0]->dailyTimes);
        $this->assertSame('devil-square', $events[0]->toClientArray()['key']);
    }

    /**
     * Confirms that minute intervals expand from their configured daily anchor.
     */
    public function test_interval_schedule_expands_and_reports_its_frequency(): void
    {
        $definitions = [
            $this->definition(
                key: 'blood-castle',
                intervalMinutes: 60,
                intervalAnchor: '00:15',
            ),
        ];
        $now = new DateTimeImmutable('2026-11-02T10:20:00+02:00');

        $events = (new EventSchedule)->upcoming($definitions, $now);

        $this->assertSame('2026-11-02T10:15:00+02:00', $events[0]->previousOccurrence->format(DATE_ATOM));
        $this->assertSame('2026-11-02T11:15:00+02:00', $events[0]->nextOccurrence()->format(DATE_ATOM));
        $this->assertSame(60, $events[0]->intervalMinutes);
    }

    /**
     * Confirms that weekly events honor ISO weekday filters and nearest-first sorting.
     */
    public function test_weekly_and_daily_events_are_sorted_by_nearest_occurrence(): void
    {
        $definitions = [
            $this->definition(
                key: 'weekly-event',
                times: ['23:59'],
                daysOfWeek: [5],
            ),
            $this->definition(
                key: 'daily-event',
                times: ['10:30'],
            ),
        ];
        $now = new DateTimeImmutable('2026-11-02T10:20:00+02:00');

        $events = (new EventSchedule)->upcoming($definitions, $now);

        $this->assertSame('daily-event', $events[0]->key);
        $this->assertSame('weekly-event', $events[1]->key);
        $this->assertSame('2026-11-06T23:59:00+02:00', $events[1]->nextOccurrence()->format(DATE_ATOM));
    }

    /**
     * Creates one typed event definition for focused schedule scenarios.
     *
     * @param  list<string>  $times
     * @param  list<int>  $daysOfWeek
     */
    private function definition(
        string $key,
        array $times = [],
        array $daysOfWeek = [],
        ?int $intervalMinutes = null,
        string $intervalAnchor = '00:00',
    ): EventDefinition {
        return new EventDefinition(
            key: $key,
            name: $key,
            location: 'Realm-wide',
            accent: 'crimson',
            enabled: true,
            durationMinutes: 5,
            times: $times,
            daysOfWeek: $daysOfWeek,
            intervalMinutes: $intervalMinutes,
            intervalAnchor: $intervalAnchor,
            source: 'test fixture',
        );
    }
}
