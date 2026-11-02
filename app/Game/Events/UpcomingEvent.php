<?php

namespace App\Game\Events;

use DateTimeImmutable;

/**
 * Carries one recurring realm event and its calculated future occurrences.
 */
final readonly class UpcomingEvent
{
    /**
     * Creates an immutable event schedule entry for the public interface.
     *
     * @param  list<DateTimeImmutable>  $occurrences
     * @param  list<string>  $dailyTimes
     * @param  list<int>  $daysOfWeek
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $location,
        public string $accent,
        public DateTimeImmutable $previousOccurrence,
        public array $occurrences,
        public array $dailyTimes,
        public array $daysOfWeek,
        public ?int $intervalMinutes,
        public int $durationMinutes,
    ) {}

    /**
     * Returns the nearest future occurrence used to sort the public cards.
     */
    public function nextOccurrence(): DateTimeImmutable
    {
        return $this->occurrences[0];
    }

    /**
     * Converts the event to the stable client shape used by the live timers.
     *
     * @return array{key: string, name: string, location: string, accent: string, previousOccurrence: string, occurrences: list<string>, dailyTimes: list<string>, daysOfWeek: list<int>, intervalMinutes: int|null, durationMinutes: int}
     */
    public function toClientArray(): array
    {
        $formattedOccurrences = [];

        foreach ($this->occurrences as $occurrence) {
            $formattedOccurrences[] = $occurrence->format(DATE_ATOM);
        }

        return [
            'key' => $this->key,
            'name' => $this->name,
            'location' => $this->location,
            'accent' => $this->accent,
            'previousOccurrence' => $this->previousOccurrence->format(DATE_ATOM),
            'occurrences' => $formattedOccurrences,
            'dailyTimes' => $this->dailyTimes,
            'daysOfWeek' => $this->daysOfWeek,
            'intervalMinutes' => $this->intervalMinutes,
            'durationMinutes' => $this->durationMinutes,
        ];
    }
}
