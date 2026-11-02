<?php

namespace App\Game\Events;

/**
 * Reads the active version-aware realm event profile from Laravel configuration.
 */
final class EventProfileReader
{
    /**
     * Returns typed events from the selected game-server profile.
     *
     * @return list<EventDefinition>
     */
    public function definitions(): array
    {
        $profileKey = (string) config('game.event_profile');
        $entries = (array) config("game_events.profiles.{$profileKey}.events", []);
        $definitions = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $definitions[] = EventDefinition::fromConfiguration($entry);
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
}
