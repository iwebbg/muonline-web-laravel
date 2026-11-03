<?php

namespace App\Game\Data;

/**
 * Carries one normalized guild ranking row across game versions.
 */
final readonly class GuildRankingEntry
{
    /**
     * Creates an immutable public guild ranking entry.
     *
     * @param  list<string>  $markColors
     */
    public function __construct(
        public string $name,
        public string $master,
        public int $score,
        public int $members,
        public array $markColors,
    ) {}

    /**
     * Converts the entry to the stable shape consumed by the ranking view.
     *
     * @return array{name: string, master: string, score: int, members: int, mark_colors: list<string>}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'master' => $this->master,
            'score' => $this->score,
            'members' => $this->members,
            'mark_colors' => $this->markColors,
        ];
    }
}
