<?php

namespace App\Game\Data;

/**
 * Carries aggregate public realm status without exposing legacy database names.
 */
final readonly class RealmOverview
{
    /**
     * Creates an immutable realm overview returned by every version adapter.
     */
    public function __construct(
        public string $name,
        public string $version,
        public string $experience,
        public string $drop,
        public int $online,
        public int $capacity,
        public int $accounts,
        public int $characters,
        public int $guilds,
        public string $dataSource,
    ) {}

    /**
     * Converts the overview to the stable shape consumed by the Blade view.
     *
     * @return array{name: string, version: string, experience: string, drop: string, online: int, capacity: int, accounts: int, characters: int, guilds: int, data_source: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'experience' => $this->experience,
            'drop' => $this->drop,
            'online' => $this->online,
            'capacity' => $this->capacity,
            'accounts' => $this->accounts,
            'characters' => $this->characters,
            'guilds' => $this->guilds,
            'data_source' => $this->dataSource,
        ];
    }
}
