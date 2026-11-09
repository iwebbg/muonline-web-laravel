<?php

namespace App\Game\Data;

/**
 * Carries one exact page of public Gens standings and its SQL-derived totals.
 */
final readonly class GensRankingPage
{
    /**
     * Creates an immutable Gens ranking page.
     *
     * @param  list<GensRankingEntry>  $entries
     */
    public function __construct(
        public array $entries,
        public int $total,
        public int $page,
        public int $perPage,
        public int $lastPage,
    ) {}
}
