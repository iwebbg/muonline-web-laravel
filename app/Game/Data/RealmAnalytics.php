<?php

namespace App\Game\Data;

/**
 * Carries a read-only server snapshot for the administrator overview.
 */
final readonly class RealmAnalytics
{
    /**
     * Creates the aggregate totals and normalized character distributions.
     *
     * @param  list<RealmDistributionEntry>  $classes
     * @param  list<RealmDistributionEntry>  $maps
     */
    public function __construct(
        public int $accounts,
        public int $characters,
        public int $guilds,
        public int $onlinePlayers,
        public array $classes,
        public array $maps,
    ) {}

    /**
     * Converts the snapshot to the stable presentation shape used by Blade.
     *
     * @return array{
     *     accounts: int,
     *     characters: int,
     *     guilds: int,
     *     online_players: int,
     *     classes: list<array{label: string, count: int, percentage: float, abbreviation: string|null}>,
     *     maps: list<array{label: string, count: int, percentage: float, abbreviation: string|null}>
     * }
     */
    public function toArray(): array
    {
        return [
            'accounts' => $this->accounts,
            'characters' => $this->characters,
            'guilds' => $this->guilds,
            'online_players' => $this->onlinePlayers,
            'classes' => array_map(
                static fn (RealmDistributionEntry $entry): array => $entry->toArray(),
                $this->classes,
            ),
            'maps' => array_map(
                static fn (RealmDistributionEntry $entry): array => $entry->toArray(),
                $this->maps,
            ),
        ];
    }
}
