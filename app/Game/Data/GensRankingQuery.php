<?php

namespace App\Game\Data;

/**
 * Carries one validated public Gens leaderboard query into an adapter.
 */
final readonly class GensRankingQuery
{
    /**
     * Creates an immutable Gens ranking query.
     */
    public function __construct(
        public int $page,
        public int $perPage,
        public string $search,
    ) {}
}
