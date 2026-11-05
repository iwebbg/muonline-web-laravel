<?php

namespace App\Game\Contracts;

use App\Game\Data\CharacterRankingEntry;
use App\Game\Data\CharacterRankingPage;
use App\Game\Data\CharacterRankingQuery;
use App\Game\Data\GuildRankingEntry;
use App\Game\Data\GuildRankingPage;
use App\Game\Data\GuildRankingQuery;
use App\Game\Data\PublicCharacterProfile;
use App\Game\Data\PublicGuildProfile;
use App\Game\Data\RealmAnalytics;
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
     * Returns aggregate totals and character distributions for administration.
     */
    public function analytics(): RealmAnalytics;

    /**
     * Returns the strongest characters ordered by version-specific progression.
     *
     * @return list<CharacterRankingEntry>
     */
    public function topCharacters(int $limit): array;

    /**
     * Returns one SQL-ordered, filtered and counted character ranking page.
     */
    public function characterRankings(CharacterRankingQuery $query): CharacterRankingPage;

    /**
     * Returns the highest-scoring guilds from the game database.
     *
     * @return list<GuildRankingEntry>
     */
    public function topGuilds(int $limit): array;

    /**
     * Returns one SQL-ordered, searched and counted guild ranking page.
     */
    public function guildRankings(GuildRankingQuery $query): GuildRankingPage;

    /**
     * Returns one privacy-safe public character profile or null when absent.
     */
    public function publicCharacter(string $characterName): ?PublicCharacterProfile;

    /**
     * Returns one public guild profile with a normalized roster or null when absent.
     */
    public function publicGuild(string $guildName): ?PublicGuildProfile;
}
