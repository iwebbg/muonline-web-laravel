<?php

namespace App\Game\Data;

use App\Game\Rankings\RankingBoard;
use App\Game\Versions\Version97d99i\CharacterClassFilter;

/**
 * Carries one validated character leaderboard query into a read repository.
 */
final readonly class CharacterRankingQuery
{
    /**
     * Creates an immutable character ranking query.
     */
    public function __construct(
        public RankingBoard $board,
        public ?CharacterClassFilter $classFilter,
        public int $page,
        public int $perPage,
        public string $search,
        public bool $onlineOnly,
    ) {}
}
