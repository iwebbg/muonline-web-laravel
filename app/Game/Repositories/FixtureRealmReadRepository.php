<?php

namespace App\Game\Repositories;

use App\Game\Contracts\RealmReadRepository;
use App\Game\Data\CharacterRankingEntry;
use App\Game\Data\CharacterRankingPage;
use App\Game\Data\CharacterRankingQuery;
use App\Game\Data\GuildRankingEntry;
use App\Game\Data\GuildRankingPage;
use App\Game\Data\GuildRankingQuery;
use App\Game\Data\PublicCharacterProfile;
use App\Game\Data\PublicGuildMember;
use App\Game\Data\PublicGuildProfile;
use App\Game\Data\RealmAnalytics;
use App\Game\Data\RealmDistributionEntry;
use App\Game\Data\RealmOverview;
use App\Game\Rankings\RankingBoard;
use App\Game\Versions\Version97d99i\GuildMarkDecoder;

/**
 * Supplies deterministic public realm data while no game database is connected.
 */
final class FixtureRealmReadRepository implements RealmReadRepository
{
    /**
     * Creates the fixture repository with the real 97d+99i guild-mark decoder.
     */
    public function __construct(
        private readonly GuildMarkDecoder $guildMarkDecoder,
    ) {}

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
     * Returns deterministic aggregate analytics for local interface development.
     */
    public function analytics(): RealmAnalytics
    {
        $characterTotal = 1620;

        return new RealmAnalytics(
            accounts: 1550,
            characters: $characterTotal,
            guilds: 114,
            onlinePlayers: 186,
            classes: [
                new RealmDistributionEntry('Blade Knight', 520, $characterTotal, 'BK'),
                new RealmDistributionEntry('Soul Master', 410, $characterTotal, 'SM'),
                new RealmDistributionEntry('Muse Elf', 330, $characterTotal, 'ME'),
                new RealmDistributionEntry('Magic Gladiator', 200, $characterTotal, 'MG'),
                new RealmDistributionEntry('Dark Knight', 80, $characterTotal, 'DK'),
                new RealmDistributionEntry('Dark Wizard', 50, $characterTotal, 'DW'),
                new RealmDistributionEntry('Fairy Elf', 30, $characterTotal, 'FE'),
            ],
            maps: [
                new RealmDistributionEntry('Lorencia', 400, $characterTotal),
                new RealmDistributionEntry('Davias', 320, $characterTotal),
                new RealmDistributionEntry('Noria', 280, $characterTotal),
                new RealmDistributionEntry('Losttower', 210, $characterTotal),
                new RealmDistributionEntry('Stadium', 160, $characterTotal),
                new RealmDistributionEntry('Atlans', 110, $characterTotal),
                new RealmDistributionEntry('Tarkan', 80, $characterTotal),
                new RealmDistributionEntry('Dungeon', 60, $characterTotal),
            ],
        );
    }

    /**
     * Returns deterministic character ranking rows for interface development.
     *
     * @return list<CharacterRankingEntry>
     */
    public function topCharacters(int $limit): array
    {
        return array_slice($this->characterEntries(), 0, $this->normalizeLimit($limit));
    }

    /**
     * Returns one filtered and paginated fixture character leaderboard.
     */
    public function characterRankings(CharacterRankingQuery $query): CharacterRankingPage
    {
        $entries = array_values(array_filter(
            $this->characterEntries(),
            function (CharacterRankingEntry $entry) use ($query): bool {
                $matchesClass = $query->classFilter === null
                    || $entry->classCode === $query->classFilter->code;
                $matchesSearch = $query->search === ''
                    || str_contains(mb_strtolower($entry->name), mb_strtolower($query->search));
                $matchesOnline = ! $query->onlineOnly || $entry->online;

                return $matchesClass && $matchesSearch && $matchesOnline;
            },
        ));

        usort(
            $entries,
            function (CharacterRankingEntry $left, CharacterRankingEntry $right) use ($query): int {
                if ($query->board === RankingBoard::PlayerKillers) {
                    return [$right->pkCount, $right->pkLevel, $left->name]
                        <=> [$left->pkCount, $left->pkLevel, $right->name];
                }

                return [$right->grandResets, $right->resets, $right->level, $left->name]
                    <=> [$left->grandResets, $left->resets, $left->level, $right->name];
            },
        );

        $total = count($entries);
        $offset = ($query->page - 1) * $query->perPage;

        return new CharacterRankingPage(
            entries: array_slice($entries, $offset, $query->perPage),
            total: $total,
            page: $query->page,
            perPage: $query->perPage,
            lastPage: max(1, (int) ceil($total / $query->perPage)),
        );
    }

    /**
     * Returns every deterministic character used by fixture leaderboards.
     *
     * @return list<CharacterRankingEntry>
     */
    private function characterEntries(): array
    {
        return [
            new CharacterRankingEntry(
                name: 'KoPeH',
                classCode: 17,
                className: 'Blade Knight',
                classAbbreviation: 'BK',
                image: '/images/characters/blade-knight.png',
                level: 400,
                resets: 20,
                grandResets: 7,
                pkCount: 12,
                pkLevel: 6,
                online: true,
            ),
            new CharacterRankingEntry(
                name: 'iwebbg',
                classCode: 1,
                className: 'Soul Master',
                classAbbreviation: 'SM',
                image: '/images/characters/soul-master.jpg',
                level: 400,
                resets: 20,
                grandResets: 5,
                pkCount: 8,
                pkLevel: 5,
                online: true,
            ),
            new CharacterRankingEntry(
                name: 'EvilDeviL',
                classCode: 48,
                className: 'Magic Gladiator',
                classAbbreviation: 'MG',
                image: '/images/characters/magic-gladiator.png',
                level: 400,
                resets: 19,
                grandResets: 5,
                pkCount: 21,
                pkLevel: 6,
                online: false,
            ),
            new CharacterRankingEntry(
                name: 'Athena',
                classCode: 33,
                className: 'Muse Elf',
                classAbbreviation: 'ME',
                image: '/images/characters/muse-elf.png',
                level: 390,
                resets: 18,
                grandResets: 4,
                pkCount: 4,
                pkLevel: 4,
                online: true,
            ),
        ];
    }

    /**
     * Returns deterministic guild ranking rows for interface development.
     *
     * @return list<GuildRankingEntry>
     */
    public function topGuilds(int $limit): array
    {
        return array_slice($this->guildEntries(), 0, $this->normalizeLimit($limit));
    }

    /**
     * Returns one searched and paginated fixture guild leaderboard.
     */
    public function guildRankings(GuildRankingQuery $query): GuildRankingPage
    {
        $search = mb_strtolower($query->search);
        $entries = array_values(array_filter(
            $this->guildEntries(),
            static fn (GuildRankingEntry $entry): bool => $search === ''
                || str_contains(mb_strtolower($entry->name), $search)
                || str_contains(mb_strtolower($entry->master), $search),
        ));
        $total = count($entries);
        $offset = ($query->page - 1) * $query->perPage;

        return new GuildRankingPage(
            entries: array_slice($entries, $offset, $query->perPage),
            total: $total,
            page: $query->page,
            perPage: $query->perPage,
            lastPage: max(1, (int) ceil($total / $query->perPage)),
        );
    }

    /**
     * Returns every deterministic guild used by fixture leaderboards.
     *
     * @return list<GuildRankingEntry>
     */
    private function guildEntries(): array
    {
        return [
            new GuildRankingEntry(
                name: 'Legends',
                master: 'KoPeH',
                score: 1840,
                members: 34,
                markColors: $this->fixtureMarkColors('1111111113133331133311111313111113133331111111111333333111111111'),
            ),
            new GuildRankingEntry(
                name: 'Immortal',
                master: 'iwebbg',
                score: 1625,
                members: 29,
                markColors: $this->fixtureMarkColors('1111111114111141141111411441144114444441144114411441144111111111'),
            ),
            new GuildRankingEntry(
                name: 'NoMercy',
                master: 'EvilDeviL',
                score: 1490,
                members: 27,
                markColors: $this->fixtureMarkColors('111111111CCCCCC11C1111C11C1CC1C11C1111C11CCCCCC11111111111111111'),
            ),
            new GuildRankingEntry(
                name: 'Valhalla',
                master: 'Athena',
                score: 1320,
                members: 21,
                markColors: $this->fixtureMarkColors('1111111116666661161111611661166116666661166116611661166111111111'),
            ),
        ];
    }

    /**
     * Returns a deterministic public character profile for fixture ranking names.
     */
    public function publicCharacter(string $characterName): ?PublicCharacterProfile
    {
        $profiles = [
            'KoPeH' => new PublicCharacterProfile(
                name: 'KoPeH',
                className: 'Blade Knight',
                image: '/images/characters/blade-knight.png',
                level: 400,
                resets: 20,
                grandResets: 7,
                map: 'Lorencia',
                pkLevel: 3,
                guildName: 'Legends',
                online: true,
                vip: true,
            ),
            'iwebbg' => new PublicCharacterProfile(
                name: 'iwebbg',
                className: 'Soul Master',
                image: '/images/characters/soul-master.jpg',
                level: 400,
                resets: 20,
                grandResets: 5,
                map: 'Davias',
                pkLevel: 3,
                guildName: 'Immortal',
                online: true,
                vip: false,
            ),
            'EvilDeviL' => new PublicCharacterProfile(
                name: 'EvilDeviL',
                className: 'Magic Gladiator',
                image: '/images/characters/magic-gladiator.png',
                level: 395,
                resets: 19,
                grandResets: 5,
                map: 'Stadium',
                pkLevel: 6,
                guildName: 'NoMercy',
                online: false,
                vip: false,
            ),
            'Athena' => new PublicCharacterProfile(
                name: 'Athena',
                className: 'Muse Elf',
                image: '/images/characters/muse-elf.png',
                level: 390,
                resets: 18,
                grandResets: 4,
                map: 'Noria',
                pkLevel: 3,
                guildName: 'Valhalla',
                online: true,
                vip: true,
            ),
        ];

        return $profiles[$characterName] ?? null;
    }

    /**
     * Returns a deterministic public guild profile for fixture ranking names.
     */
    public function publicGuild(string $guildName): ?PublicGuildProfile
    {
        $profiles = [
            'Legends' => new PublicGuildProfile(
                name: 'Legends',
                master: 'KoPeH',
                score: 1840,
                notice: 'Strength through unity.',
                markColors: $this->fixtureMarkColors('1111111113133331133311111313111113133331111111111333333111111111'),
                members: [
                    new PublicGuildMember(
                        name: 'KoPeH',
                        className: 'Blade Knight',
                        level: 400,
                        resets: 20,
                        grandResets: 7,
                        online: true,
                        master: true,
                    ),
                    new PublicGuildMember(
                        name: 'Athena',
                        className: 'Muse Elf',
                        level: 390,
                        resets: 18,
                        grandResets: 4,
                        online: true,
                        master: false,
                    ),
                ],
            ),
        ];

        return $profiles[$guildName] ?? null;
    }

    /**
     * Keeps public ranking requests inside a safe and predictable range.
     */
    private function normalizeLimit(int $limit): int
    {
        return max(1, min($limit, 100));
    }

    /**
     * Decodes one readable hexadecimal fixture into the production mark shape.
     *
     * @return list<string>
     */
    private function fixtureMarkColors(string $hexadecimalMark): array
    {
        $binaryMark = hex2bin($hexadecimalMark);

        return $this->guildMarkDecoder->colors(
            is_string($binaryMark) ? $binaryMark : '',
        );
    }
}
