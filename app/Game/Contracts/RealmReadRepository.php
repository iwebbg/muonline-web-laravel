<?php

namespace App\Game\Contracts;

use App\Game\Data\CharacterRankingEntry;
use App\Game\Data\GuildRankingEntry;
use App\Game\Data\RealmOverview;

/**
 * Defines read-only public data available from a supported MuOnline database.
 */
interface RealmReadRepository
{
    /**
     * Returns the aggregate realm status displayed on the public dashboard.
     */
    public function overview(): RealmOverview;

    /**
     * Returns the strongest characters ordered by version-specific progression.
     *
     * @return list<CharacterRankingEntry>
     */
    public function topCharacters(int $limit): array;

    /**
     * Returns the highest-scoring guilds from the game database.
     *
     * @return list<GuildRankingEntry>
     */
    public function topGuilds(int $limit): array;
}
