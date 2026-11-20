<?php

namespace App\Game\Events;

use DateTimeImmutable;

/**
 * Describes a standard activity without claiming a live dungeon or campaign state.
 */
final readonly class EventActivity
{
    /**
     * Carries localized entry information and its source-backed availability.
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $location,
        public EventActivityAvailability $availability,
        public string $description,
        public string $source,
    ) {}

    /**
     * Resolves a configured activity and refuses to advertise an expired campaign as active.
     *
     * @param  array{key: string, name: string, location: string, availability: string, description: array<string, string>, source: string, starts_at?: string, ends_at?: string}  $configuration
     */
    public static function fromConfiguration(array $configuration, DateTimeImmutable $now, string $locale): self
    {
        $availability = EventActivityAvailability::from($configuration['availability']);
        $startsAt = isset($configuration['starts_at'])
            ? new DateTimeImmutable($configuration['starts_at'], $now->getTimezone())
            : null;
        $endsAt = isset($configuration['ends_at'])
            ? new DateTimeImmutable($configuration['ends_at'], $now->getTimezone())
            : null;

        if ($availability !== EventActivityAvailability::Disabled) {
            if ($endsAt !== null && $now > $endsAt) {
                $availability = EventActivityAvailability::Closed;
            } elseif ($startsAt !== null && $now < $startsAt) {
                $availability = EventActivityAvailability::Scheduled;
            }
        }

        return new self(
            key: $configuration['key'],
            name: $configuration['name'],
            location: $configuration['location'],
            availability: $availability,
            description: $configuration['description'][$locale] ?? $configuration['description']['en'],
            source: $configuration['source'],
        );
    }
}
