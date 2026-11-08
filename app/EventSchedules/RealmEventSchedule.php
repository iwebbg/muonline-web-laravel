<?php

namespace App\EventSchedules;

use App\CastleSiege\CastleSiegeEventProvider;
use App\Game\Events\EventDefinition;
use App\Game\Events\EventSchedule;
use App\Game\Events\UpcomingEvent;
use DateTimeImmutable;

/**
 * Combines configured recurring events with version-owned dynamic realm events.
 */
final readonly class RealmEventSchedule
{
    /**
     * Creates the aggregate schedule from static and Castle Siege sources.
     */
    public function __construct(
        private EventSchedule $eventSchedule,
        private CastleSiegeEventProvider $castleSiegeEventProvider,
    ) {}

    /**
     * Returns every available event ordered by its nearest future occurrence.
     *
     * @param  list<EventDefinition>  $definitions
     * @return list<UpcomingEvent>
     */
    public function upcoming(array $definitions, DateTimeImmutable $now): array
    {
        $events = $this->eventSchedule->upcoming($definitions, $now);
        $castleSiege = $this->castleSiegeEventProvider->upcoming($now);

        if ($castleSiege !== null) {
            $events[] = $castleSiege;
        }

        usort(
            $events,
            static fn (UpcomingEvent $left, UpcomingEvent $right): int => [
                $left->nextOccurrence()->getTimestamp(),
                $left->key,
            ] <=> [
                $right->nextOccurrence()->getTimestamp(),
                $right->key,
            ],
        );

        return $events;
    }
}
