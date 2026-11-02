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
