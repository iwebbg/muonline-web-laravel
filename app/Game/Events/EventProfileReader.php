<?php

namespace App\Game\Events;

use App\Models\EventScheduleEntry;
use Illuminate\Database\DatabaseManager;

/**
 * Reads the active version-aware realm event profile from Laravel configuration.
 */
final readonly class EventProfileReader
{
    /**
     * Creates the reader with access to application-owned schedule overrides.
     */
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * Returns typed events from the selected game-server profile.
     *
     * @return list<EventDefinition>
     */
    public function definitions(): array
    {
        $profileKey = (string) config('game.event_profile');
        $configuredEntries = (array) config("game_events.profiles.{$profileKey}.events", []);
        $entries = [];

        foreach ($configuredEntries as $position => $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = (string) ($entry['key'] ?? '');

            if ($key !== '') {
                $entries[$key] = [
                    'configuration' => $entry,
                    'sort_order' => $position,
                ];
            }
        }

        foreach ($this->persistedEntries($profileKey) as $entry) {
            $entries[$entry->event_key] = [
                'configuration' => $this->configurationFor($entry),
                'sort_order' => $entry->sort_order,
            ];
        }

        uasort(
            $entries,
            fn (array $left, array $right): int => $left['sort_order'] <=> $right['sort_order'],
        );
        $definitions = [];

        foreach ($entries as $entry) {
            $definitions[] = EventDefinition::fromConfiguration($entry['configuration']);
        }

        return $definitions;
    }

    /**
     * Returns the number of nearest events displayed before the schedule expands.
     */
    public function homeLimit(): int
    {
        return max(1, min((int) config('game.event_home_limit'), 12));
    }

    /**
     * Returns application-owned overrides when their migration is available.
     *
     * @return iterable<int, EventScheduleEntry>
     */
    private function persistedEntries(string $profileKey): iterable
    {
        $table = (new EventScheduleEntry)->getTable();
        $schema = $this->database->connection()->getSchemaBuilder();

        if (! $schema->hasTable($table)) {
            return [];
        }

        return EventScheduleEntry::query()
            ->where('profile_key', $profileKey)
            ->where('game_version', (string) config('game.profile.version'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Converts one localized persisted override to the scheduler contract.
     *
     * @return array<string, mixed>
     */
    private function configurationFor(EventScheduleEntry $entry): array
    {
        $intervalMode = $entry->schedule_mode === 'interval';

        return [
            'key' => $entry->event_key,
            'name' => $entry->localizedName(app()->getLocale()),
            'location' => $entry->localizedLocation(app()->getLocale()),
            'accent' => $entry->accent,
            'enabled' => $entry->enabled,
            'duration_minutes' => $entry->duration_minutes,
            'times' => $intervalMode ? [] : $entry->times,
            'days_of_week' => $entry->days_of_week,
            'interval_minutes' => $intervalMode ? $entry->interval_minutes : null,
            'interval_anchor' => $entry->interval_anchor,
            'source' => $entry->source ?? '',
        ];
    }
}
