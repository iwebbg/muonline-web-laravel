<?php

namespace App\CastleSiege;

/**
 * Carries the reviewed Castle Siege phase timing for one game-server profile.
 */
final readonly class CastleSiegeScheduleDefinition
{
    /**
     * Creates one typed Castle Siege event definition.
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $location,
        public string $accent,
        public bool $enabled,
        public int $mapServerGroup,
        public int $cycleDays,
        public int $battleDay,
        public int $battleHour,
        public int $battleMinute,
        public int $durationMinutes,
    ) {}

    /**
     * Converts one trusted profile entry to a validated schedule definition.
     *
     * @param  array<string, mixed>  $configuration
     */
    public static function fromConfiguration(array $configuration): self
    {
        $battleTime = (string) ($configuration['battle_time'] ?? '00:00');
        $battleHour = 0;
        $battleMinute = 0;

        if (preg_match('/\A([01]\d|2[0-3]):([0-5]\d)\z/', $battleTime, $matches) === 1) {
            $battleHour = (int) $matches[1];
            $battleMinute = (int) $matches[2];
        }

        return new self(
            key: (string) ($configuration['key'] ?? 'castle-siege'),
            name: (string) ($configuration['name'] ?? 'Castle Siege'),
            location: (string) ($configuration['location'] ?? 'Valley of Loren'),
            accent: (string) ($configuration['accent'] ?? 'amber'),
            enabled: (bool) ($configuration['enabled'] ?? true),
            mapServerGroup: max(0, (int) ($configuration['map_server_group'] ?? 0)),
            cycleDays: max(1, min((int) ($configuration['cycle_days'] ?? 14), 31)),
            battleDay: max(0, (int) ($configuration['battle_day'] ?? 0)),
            battleHour: $battleHour,
            battleMinute: $battleMinute,
            durationMinutes: max(0, (int) ($configuration['duration_minutes'] ?? 0)),
        );
    }

    /**
     * Returns the server-local battle time displayed in the full schedule.
     */
    public function battleTime(): string
    {
        return sprintf('%02d:%02d', $this->battleHour, $this->battleMinute);
    }
}
