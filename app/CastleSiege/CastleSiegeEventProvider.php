<?php

namespace App\CastleSiege;

use App\Game\Contracts\CastleSiegeReadRepository;
use App\Game\Events\UpcomingEvent;
use App\Game\Versions\Contracts\GameVersionProfile;
use DateTimeImmutable;

/**
 * Builds the next Castle Siege occurrences from server-owned cycle state.
 */
final readonly class CastleSiegeEventProvider
{
    /**
     * Creates the provider with the selected version and its read-only repository.
     */
    public function __construct(
        private CastleSiegeReadRepository $castleSiegeReadRepository,
        private GameVersionProfile $gameVersionProfile,
    ) {}

    /**
     * Returns a dynamic Castle Siege event when the active version and database support it.
     */
    public function upcoming(DateTimeImmutable $now): ?UpcomingEvent
    {
        if (! $this->gameVersionProfile->capabilities()->castleSiege) {
            return null;
        }

        $profileKey = (string) config('game.event_profile');
        $configuration = config("game_events.profiles.{$profileKey}.castle_siege");

        if (! is_array($configuration)) {
            return null;
        }

        $definition = CastleSiegeScheduleDefinition::fromConfiguration($configuration);

        if (! $this->isValid($definition)) {
            return null;
        }

        $state = $this->castleSiegeReadRepository->state(
            mapServerGroup: $definition->mapServerGroup,
            timezone: $now->getTimezone(),
        );

        if ($state === null) {
            return null;
        }

        $databaseCycleDays = $state->cycleStartsAt
            ->diff($state->cycleEndsAt)
            ->days;
        $cycleDays = is_int($databaseCycleDays) && $databaseCycleDays > 0
            ? $databaseCycleDays
            : $definition->cycleDays;
        $battleAnchor = $state->cycleStartsAt
            ->modify(sprintf('+%d days', $definition->battleDay))
            ->setTime($definition->battleHour, $definition->battleMinute);
        $previousOccurrence = $this->previousOccurrence(
            anchor: $battleAnchor,
            now: $now,
            cycleDays: $cycleDays,
        );
        $occurrences = [
            $previousOccurrence->modify(sprintf('+%d days', $cycleDays)),
            $previousOccurrence->modify(sprintf('+%d days', $cycleDays * 2)),
        ];

        return new UpcomingEvent(
            key: $definition->key,
            name: $definition->name,
            location: $definition->location,
            accent: $definition->accent,
            previousOccurrence: $previousOccurrence,
            occurrences: $occurrences,
            dailyTimes: [$definition->battleTime()],
            daysOfWeek: [],
            intervalMinutes: null,
            durationMinutes: $definition->durationMinutes,
            cycleDays: $cycleDays,
        );
    }

    /**
     * Rejects incomplete phase definitions before any game database query runs.
     */
    private function isValid(CastleSiegeScheduleDefinition $definition): bool
    {
        return $definition->enabled
            && $definition->key !== ''
            && $definition->name !== ''
            && $definition->battleDay < $definition->cycleDays;
    }

    /**
     * Finds the nearest battle start at or before the supplied server time.
     */
    private function previousOccurrence(
        DateTimeImmutable $anchor,
        DateTimeImmutable $now,
        int $cycleDays,
    ): DateTimeImmutable {
        $cycleSeconds = $cycleDays * 86_400;
        $elapsedSeconds = $now->getTimestamp() - $anchor->getTimestamp();
        $estimatedCycle = intdiv($elapsedSeconds, $cycleSeconds);

        if ($elapsedSeconds < 0 && $elapsedSeconds % $cycleSeconds !== 0) {
            $estimatedCycle--;
        }

        $occurrence = $this->occurrenceForCycle($anchor, $estimatedCycle, $cycleDays);

        while ($occurrence > $now) {
            $estimatedCycle--;
            $occurrence = $this->occurrenceForCycle($anchor, $estimatedCycle, $cycleDays);
        }

        $nextOccurrence = $this->occurrenceForCycle($anchor, $estimatedCycle + 1, $cycleDays);

        while ($nextOccurrence <= $now) {
            $estimatedCycle++;
            $occurrence = $nextOccurrence;
            $nextOccurrence = $this->occurrenceForCycle($anchor, $estimatedCycle + 1, $cycleDays);
        }

        return $occurrence;
    }

    /**
     * Applies one whole server-local cycle without assuming every day has 86,400 seconds.
     */
    private function occurrenceForCycle(
        DateTimeImmutable $anchor,
        int $cycleIndex,
        int $cycleDays,
    ): DateTimeImmutable {
        $dayOffset = $cycleIndex * $cycleDays;
        $modifier = sprintf('%+d days', $dayOffset);

        return $anchor->modify($modifier);
    }
}
