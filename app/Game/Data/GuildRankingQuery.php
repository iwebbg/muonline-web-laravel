<?php

namespace App\Game\Data;

/**
 * Carries one validated guild leaderboard query into a read repository.
 */
final readonly class GuildRankingQuery
{
    /**
     * Creates an immutable guild ranking query.
     */
    public function __construct(
        public int $page,
        public int $perPage,
        public string $search,
    ) {}
}
