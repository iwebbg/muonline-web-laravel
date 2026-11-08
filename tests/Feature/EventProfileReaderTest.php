<?php

namespace Tests\Feature;

use App\Game\Events\EventProfileReader;
use App\Models\EventScheduleEntry;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies config fallback and localized database overrides for event profiles.
 */
final class EventProfileReaderTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms reviewed config definitions remain active before any override exists.
     */
    public function test_reader_uses_config_definitions_without_database_overrides(): void
    {
        $definitions = $this->app->make(EventProfileReader::class)->definitions();

        $this->assertCount(8, $definitions);
        $this->assertSame('blood-castle', $definitions[0]->key);
        $this->assertSame('Blood Castle', $definitions[0]->name);
    }

    /**
     * Confirms the 1.0M profile exposes every enabled event with a verified schedule.
     */
    public function test_reader_uses_complete_1_0m_server_file_schedule(): void
    {
        config()->set('game.event_profile', 'darksteam_1_0m');
        config()->set('game.profile.version', '0.99.60/62T (1.0M)');

        $definitions = $this->app->make(EventProfileReader::class)->definitions();
        $definitionsByKey = [];

        foreach ($definitions as $definition) {
            $definitionsByKey[$definition->key] = $definition;
        }

        $this->assertSame([
            'blood-castle',
            'devil-square',
            'chaos-castle',
            'skeleton-king-invasion',
            'golden-dragon-invasion',
            'white-wizard-invasion',
            'happy-hour',
            'sky-event',
            'happy-level-up',
            'castle-deep',
        ], array_keys($definitionsByKey));
        $this->assertSame(120, $definitionsByKey['blood-castle']->intervalMinutes);
        $this->assertSame('00:00', $definitionsByKey['blood-castle']->intervalAnchor);
        $this->assertSame(15, $definitionsByKey['blood-castle']->durationMinutes);
        $this->assertSame(120, $definitionsByKey['devil-square']->intervalMinutes);
        $this->assertSame('00:30', $definitionsByKey['devil-square']->intervalAnchor);
        $this->assertSame(20, $definitionsByKey['devil-square']->durationMinutes);
        $this->assertSame(
            ['12:00', '14:00', '16:00', '18:00', '20:00', '22:00'],
            $definitionsByKey['chaos-castle']->times,
        );
        $this->assertSame([5], $definitionsByKey['happy-hour']->daysOfWeek);
        $this->assertSame(['00:00'], $definitionsByKey['happy-hour']->times);
        $this->assertSame(['20:00'], $definitionsByKey['sky-event']->times);
        $this->assertSame(['14:00'], $definitionsByKey['happy-level-up']->times);
        $this->assertSame(['21:30'], $definitionsByKey['castle-deep']->times);
        $this->assertSame(
            ['00:00', '02:00', '04:00', '08:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00'],
            $definitionsByKey['white-wizard-invasion']->times,
        );
    }

    /**
     * Confirms a database record replaces its config key and uses Bulgarian text.
     */
    public function test_reader_merges_localized_override_and_custom_event(): void
    {
        EventScheduleEntry::query()->create($this->entryAttributes(
            eventKey: 'blood-castle',
            nameEn: 'Blood Castle Override',
            nameBg: 'Кървав замък',
            sortOrder: 0,
        ));
        EventScheduleEntry::query()->create($this->entryAttributes(
            eventKey: 'custom-arena',
            nameEn: 'Custom Arena',
            nameBg: null,
            sortOrder: 100,
        ));
        app()->setLocale('bg');

        $definitions = $this->app->make(EventProfileReader::class)->definitions();
        $definitionsByKey = [];

        foreach ($definitions as $definition) {
            $definitionsByKey[$definition->key] = $definition;
        }

        $this->assertCount(9, $definitions);
        $this->assertSame('Кървав замък', $definitionsByKey['blood-castle']->name);
        $this->assertSame('Custom Arena', $definitionsByKey['custom-arena']->name);
        $this->assertSame(90, $definitionsByKey['custom-arena']->intervalMinutes);
    }

    /**
     * Builds one persisted interval event for the active profile.
     *
     * @return array<string, mixed>
     */
    private function entryAttributes(
        string $eventKey,
        string $nameEn,
        ?string $nameBg,
        int $sortOrder,
    ): array {
        return [
            'profile_key' => 'darksteam_97d99i',
            'game_version' => '97d + 99i',
            'event_key' => $eventKey,
            'name_en' => $nameEn,
            'name_bg' => $nameBg,
            'location_en' => 'Arena',
            'location_bg' => 'Арена',
            'accent' => 'amber',
            'enabled' => true,
            'duration_minutes' => 15,
            'schedule_mode' => 'interval',
            'times' => [],
            'days_of_week' => [],
            'interval_minutes' => 90,
            'interval_anchor' => '00:15',
            'source' => 'Test override',
            'sort_order' => $sortOrder,
            'revision' => 1,
        ];
    }
}
