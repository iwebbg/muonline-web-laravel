<?php

namespace App\Game\Events;

/**
 * Carries one validated version-profile event without leaking config arrays.
 */
final readonly class EventDefinition
{
    /**
     * Creates a recurring event definition consumed by the schedule calculator.
     *
     * @param  list<string>  $times
     * @param  list<int>  $daysOfWeek  ISO-8601 weekday numbers from 1 to 7.
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $location,
        public string $accent,
        public bool $enabled,
        public int $durationMinutes,
        public array $times,
        public array $daysOfWeek,
        public ?int $intervalMinutes,
        public string $intervalAnchor,
        public string $source,
    ) {}

    /**
     * Converts one trusted application config entry to a typed definition.
     *
     * @param  array<string, mixed>  $configuration
     */
    public static function fromConfiguration(array $configuration): self
    {
        return new self(
            key: (string) ($configuration['key'] ?? ''),
            name: (string) ($configuration['name'] ?? ''),
            location: (string) ($configuration['location'] ?? ''),
            accent: (string) ($configuration['accent'] ?? 'crimson'),
            enabled: (bool) ($configuration['enabled'] ?? true),
            durationMinutes: max(0, (int) ($configuration['duration_minutes'] ?? 0)),
            times: array_values((array) ($configuration['times'] ?? [])),
            daysOfWeek: array_values((array) ($configuration['days_of_week'] ?? [])),
            intervalMinutes: isset($configuration['interval_minutes'])
                ? (int) $configuration['interval_minutes']
                : null,
            intervalAnchor: (string) ($configuration['interval_anchor'] ?? '00:00'),
            source: (string) ($configuration['source'] ?? ''),
        );
    }
}
