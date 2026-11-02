<?php

namespace App\Game\Data;

/**
 * Carries one normalized guild ranking row across game versions.
 */
final readonly class GuildRankingEntry
{
    /**
     * Creates an immutable public guild ranking entry.
     */
    public function __construct(
        public string $name,
        public string $master,
        public int $score,
        public int $members,
    ) {}

    /**
     * Converts the entry to the stable shape consumed by the ranking view.
     *
     * @return array{name: string, master: string, score: int, members: int}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'master' => $this->master,
            'score' => $this->score,
            'members' => $this->members,
        ];
    }
}
