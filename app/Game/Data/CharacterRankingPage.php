<?php

namespace App\Game\Data;

/**
 * Carries one exact page of character rankings and its SQL-derived totals.
 */
final readonly class CharacterRankingPage
{
    /**
     * Creates an immutable character ranking page.
     *
     * @param  list<CharacterRankingEntry>  $entries
     */
    public function __construct(
        public array $entries,
        public int $total,
        public int $page,
        public int $perPage,
        public int $lastPage,
    ) {}
}
