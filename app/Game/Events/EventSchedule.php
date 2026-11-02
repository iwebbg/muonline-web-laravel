<?php

namespace App\Game\Events;

use DateTimeImmutable;

/**
 * Calculates recurring version-profile events from typed definitions.
 */
final class EventSchedule
{
    /**
     * Returns future occurrences and the previous occurrence for every valid event.
     *
     * @param  list<EventDefinition>  $definitions
     * @return list<UpcomingEvent>
     */
    public function upcoming(
        array $definitions,
        DateTimeImmutable $now,
        int $horizonDays = 7,
    ): array {
        $events = [];
        $normalizedHorizon = max(1, min($horizonDays, 31));

        foreach ($definitions as $definition) {
            if (! $definition->enabled || $definition->key === '' || $definition->name === '') {
                continue;
            }

            $event = $this->buildEvent(
                definition: $definition,
                now: $now,
                horizonDays: $normalizedHorizon,
            );

            if ($event !== null) {
                $events[] = $event;
            }
        }

        return $this->sortByNextOccurrence($events);
    }

    /**
     * Builds one normalized recurring event or skips a definition without valid times.
     */
    private function buildEvent(
        EventDefinition $definition,
        DateTimeImmutable $now,
        int $horizonDays,
    ): ?UpcomingEvent {
        $dailyTimes = $this->dailyTimesFor($definition);
        $daysOfWeek = $this->validDaysOfWeek($definition->daysOfWeek);

        if ($dailyTimes === []) {
            return null;
        }

        $previousOccurrence = null;
        $futureOccurrences = [];

        for ($dayOffset = -7; $dayOffset <= $horizonDays; $dayOffset++) {
            $eventDay = $now->modify($dayOffset.' days');

            if ($daysOfWeek !== [] && ! in_array((int) $eventDay->format('N'), $daysOfWeek, true)) {
                continue;
            }

            foreach ($dailyTimes as $dailyTime) {
                $occurrence = $this->occurrenceOn($eventDay, $dailyTime);

                if ($occurrence <= $now) {
                    $previousOccurrence = $occurrence;

                    continue;
                }

                $futureOccurrences[] = $occurrence;
            }
        }

        if ($previousOccurrence === null || $futureOccurrences === []) {
            return null;
        }

        return new UpcomingEvent(
            key: $definition->key,
            name: $definition->name,
            location: $definition->location,
            accent: $definition->accent,
            previousOccurrence: $previousOccurrence,
            occurrences: $futureOccurrences,
            dailyTimes: $dailyTimes,
            daysOfWeek: $daysOfWeek,
            intervalMinutes: $definition->intervalMinutes,
            durationMinutes: $definition->durationMinutes,
        );
    }

    /**
     * Resolves explicit times or expands a configurable minute interval.
     *
     * @return list<string>
     */
    private function dailyTimesFor(EventDefinition $definition): array
    {
        if ($definition->intervalMinutes === null) {
            return $this->validDailyTimes($definition->times);
        }

        $intervalMinutes = $definition->intervalMinutes;
        $anchorTimes = $this->validDailyTimes([$definition->intervalAnchor]);

        if ($intervalMinutes < 1 || $intervalMinutes > 1440 || $anchorTimes === []) {
            return [];
        }

        [$anchorHour, $anchorMinute] = array_map('intval', explode(':', $anchorTimes[0]));
        $anchorTotalMinutes = ($anchorHour * 60) + $anchorMinute;
        $dailyTimes = [];

        for ($minute = $anchorTotalMinutes; $minute < 1440; $minute += $intervalMinutes) {
            $hour = intdiv($minute, 60);
            $minuteWithinHour = $minute % 60;
            $dailyTimes[] = sprintf('%02d:%02d', $hour, $minuteWithinHour);
        }

        return $dailyTimes;
    }

    /**
     * Keeps calendar schedules within ISO-8601 weekday numbers.
     *
     * @param  list<int>  $daysOfWeek
     * @return list<int>
     */
    private function validDaysOfWeek(array $daysOfWeek): array
    {
        $validDays = [];

        foreach ($daysOfWeek as $dayOfWeek) {
            $normalizedDay = (int) $dayOfWeek;

            if ($normalizedDay < 1 || $normalizedDay > 7) {
                continue;
            }

            $validDays[] = $normalizedDay;
        }

        $validDays = array_values(array_unique($validDays));

        sort($validDays, SORT_NUMERIC);

        return $validDays;
    }

    /**
     * Sorts events by their nearest upcoming occurrence with a stable key tie-breaker.
     *
     * @param  list<UpcomingEvent>  $events
     * @return list<UpcomingEvent>
     */
    private function sortByNextOccurrence(array $events): array
    {
        $eventsByTime = [];

        foreach ($events as $event) {
            $sortKey = $event->nextOccurrence()->format('U.u').'-'.$event->key;
            $eventsByTime[$sortKey] = $event;
        }

        ksort($eventsByTime, SORT_STRING);

        return array_values($eventsByTime);
    }

    /**
     * Filters and sorts trusted schedule values that use a valid 24-hour format.
     *
     * @param  list<string>  $dailyTimes
     * @return list<string>
     */
    private function validDailyTimes(array $dailyTimes): array
    {
        $validTimes = [];

        foreach ($dailyTimes as $dailyTime) {
            if (! is_string($dailyTime)) {
                continue;
            }

            if (preg_match('/\A(?:[01]\d|2[0-3]):[0-5]\d\z/', $dailyTime) !== 1) {
                continue;
            }

            $validTimes[] = $dailyTime;
        }

        $validTimes = array_values(array_unique($validTimes));

        sort($validTimes, SORT_STRING);

        return $validTimes;
    }

    /**
     * Creates one occurrence on the supplied local server day.
     */
    private function occurrenceOn(DateTimeImmutable $eventDay, string $dailyTime): DateTimeImmutable
    {
        [$hour, $minute] = array_map('intval', explode(':', $dailyTime));

        return $eventDay->setTime($hour, $minute);
    }
}
