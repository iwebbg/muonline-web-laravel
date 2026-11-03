<?php

namespace App\Game\Data;

/**
 * Carries one exact page of guild rankings and its SQL-derived totals.
 */
final readonly class GuildRankingPage
{
    /**
     * Creates an immutable guild ranking page.
     *
     * @param  list<GuildRankingEntry>  $entries
     */
    public function __construct(
        public array $entries,
        public int $total,
        public int $page,
        public int $perPage,
        public int $lastPage,
    ) {}
}
