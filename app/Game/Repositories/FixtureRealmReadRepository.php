<?php

namespace App\Game\Repositories;

use App\Game\Contracts\RealmReadRepository;
use App\Game\Data\CharacterRankingEntry;
use App\Game\Data\GuildRankingEntry;
use App\Game\Data\RealmOverview;

/**
 * Supplies deterministic public realm data while no game database is connected.
 */
final class FixtureRealmReadRepository implements RealmReadRepository
{
    /**
     * Returns the fixture realm overview used by local development and tests.
     */
    public function overview(): RealmOverview
    {
        return new RealmOverview(
            name: (string) config('game.profile.name'),
            version: (string) config('game.profile.version'),
            experience: (string) config('game.profile.experience'),
            drop: (string) config('game.profile.drop'),
            online: 186,
            capacity: (int) config('game.profile.capacity'),
            accounts: 1550,
            characters: 1620,
            guilds: 114,
            dataSource: 'fixture',
        );
    }

    /**
     * Returns deterministic character ranking rows for interface development.
     *
     * @return list<CharacterRankingEntry>
     */
    public function topCharacters(int $limit): array
    {
        $entries = [
            new CharacterRankingEntry(
                name: 'KoPeH',
                className: 'Blade Knight',
                resets: 20,
                grandResets: 7,
                online: true,
            ),
            new CharacterRankingEntry(
                name: 'iwebbg',
                className: 'Soul Master',
                resets: 20,
                grandResets: 5,
                online: true,
            ),
            new CharacterRankingEntry(
                name: 'EvilDeviL',
                className: 'Magic Gladiator',
                resets: 19,
                grandResets: 5,
                online: false,
            ),
            new CharacterRankingEntry(
                name: 'Athena',
                className: 'Muse Elf',
                resets: 18,
                grandResets: 4,
                online: true,
            ),
        ];

        return array_slice($entries, 0, $this->normalizeLimit($limit));
    }

    /**
     * Returns deterministic guild ranking rows for interface development.
     *
     * @return list<GuildRankingEntry>
     */
    public function topGuilds(int $limit): array
    {
        $entries = [
            new GuildRankingEntry(
                name: 'Legends',
                master: 'KoPeH',
                score: 1840,
                members: 34,
            ),
            new GuildRankingEntry(
                name: 'Immortal',
                master: 'iwebbg',
                score: 1625,
                members: 29,
            ),
            new GuildRankingEntry(
                name: 'NoMercy',
                master: 'EvilDeviL',
                score: 1490,
                members: 27,
            ),
            new GuildRankingEntry(
                name: 'Valhalla',
                master: 'Athena',
                score: 1320,
                members: 21,
            ),
        ];

        return array_slice($entries, 0, $this->normalizeLimit($limit));
    }

    /**
     * Keeps public ranking requests inside a safe and predictable range.
     */
    private function normalizeLimit(int $limit): int
    {
        return max(1, min($limit, 100));
    }
}
