<?php

namespace Tests\Feature;

use App\Game\Events\EventProfileReader;
use App\Game\Events\EventSchedule;
use App\Models\EventScheduleEntry;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies source-backed S21 schedules through the shared reader and timer calculator.
 */
final class MuDevsSeason21EventProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Selects S21 event metadata without enabling the incomplete game adapter.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.event_profile', 'mudevs_season_21');
        config()->set('game.profile.version', 'S21.1-2');
    }

    /**
     * Publishes only reviewed standard recurrences, excluding unresolved variants and campaigns.
     */
    public function test_reader_exposes_reviewed_standard_events_without_speculative_timers(): void
    {
        $definitions = app(EventProfileReader::class)->definitions();
        $events = (new EventSchedule)->upcoming($definitions, new DateTimeImmutable('2026-11-14T00:00:00+02:00'));

        $this->assertSame([
            'blood-castle', 'devil-square', 'chaos-castle', 'castle-deep',
            'moss-merchant', 'group-boss-battle', 'skeleton-king-invasion',
            'red-dragon-invasion', 'golden-invasion', 'white-wizard-invasion', 'medusa',
        ], array_column($definitions, 'key'));
        $this->assertCount(11, $events);
        $this->assertSame('group-boss-battle', $events[0]->key);
        $this->assertSame('2026-11-14T00:05:00+02:00', $events[0]->toClientArray()['occurrences'][0]);
    }

    /**
     * Retains the pack's irregular Blood Castle and Chaos Castle starts exactly.
     */
    public function test_countdowns_keep_irregular_native_start_times(): void
    {
        $definitions = app(EventProfileReader::class)->definitions();
        $calculator = new EventSchedule;
        $earlyEvents = collect($calculator->upcoming($definitions, new DateTimeImmutable('2026-11-14T01:05:59+02:00')))->keyBy('key');
        $laterEvents = collect($calculator->upcoming($definitions, new DateTimeImmutable('2026-11-14T11:23:59+02:00')))->keyBy('key');

        $this->assertSame('2026-11-14T01:06:00+02:00', $earlyEvents['blood-castle']->nextOccurrence()->format(DATE_ATOM));
        $this->assertSame('2026-11-13T22:00:00+02:00', $earlyEvents['blood-castle']->previousOccurrence->format(DATE_ATOM));
        $this->assertSame('2026-11-14T11:24:00+02:00', $laterEvents['chaos-castle']->nextOccurrence()->format(DATE_ATOM));
        $this->assertContains('15:03', $laterEvents['blood-castle']->dailyTimes);
        $this->assertContains('17:13', $laterEvents['chaos-castle']->dailyTimes);
    }

    /**
     * Uses the enabled main-server Moss duration instead of the disabled CS duration.
     */
    public function test_moss_uses_its_enabled_host_and_wraps_to_the_next_day(): void
    {
        $definitions = app(EventProfileReader::class)->definitions();
        $events = collect((new EventSchedule)->upcoming($definitions, new DateTimeImmutable('2026-11-14T23:59:59+02:00')))->keyBy('key');

        $this->assertSame(120, $events['moss-merchant']->durationMinutes);
        $this->assertSame('2026-11-15T02:00:00+02:00', $events['moss-merchant']->nextOccurrence()->format(DATE_ATOM));
        $this->assertSame(30, $events['castle-deep']->durationMinutes);
        $this->assertSame('2026-11-15T00:05:00+02:00', $events['group-boss-battle']->nextOccurrence()->format(DATE_ATOM));
        $this->assertSame('2026-11-15T01:00:00+02:00', $events['medusa']->nextOccurrence()->format(DATE_ATOM));
    }

    /**
     * Applies S21 administrator overrides without inheriting another version's event changes.
     */
    public function test_overrides_remain_scoped_to_the_s21_profile_and_version(): void
    {
        $attributes = [
            'profile_key' => 'mudevs_season_21',
            'game_version' => 'S21.1-2',
            'event_key' => 'blood-castle',
            'name_en' => 'Blood Castle',
            'location_en' => 'Blood Castle',
            'accent' => 'crimson',
            'enabled' => false,
            'duration_minutes' => 15,
            'schedule_mode' => 'times',
            'times' => ['02:15'],
            'days_of_week' => [],
            'interval_minutes' => null,
            'interval_anchor' => '00:00',
            'sort_order' => 0,
        ];
        EventScheduleEntry::query()->create($attributes);
        EventScheduleEntry::query()->create([
            ...$attributes,
            'profile_key' => 'muemu_season_6',
            'game_version' => 'Season 6',
            'event_key' => 'devil-square',
        ]);

        $definitions = app(EventProfileReader::class)->definitions();
        $events = collect((new EventSchedule)->upcoming($definitions, new DateTimeImmutable('2026-11-14T00:00:00+02:00')))->keyBy('key');

        $this->assertCount(11, $definitions);
        $this->assertCount(10, $events);
        $this->assertFalse($events->has('blood-castle'));
        $this->assertTrue($events->has('devil-square'));
    }
}
