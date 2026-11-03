<?php

namespace App\Game\Repositories;

use App\Game\Contracts\RealmReadRepository;
use App\Game\Data\CharacterRankingEntry;
use App\Game\Data\GuildRankingEntry;
use App\Game\Data\PublicCharacterProfile;
use App\Game\Data\PublicGuildMember;
use App\Game\Data\PublicGuildProfile;
use App\Game\Data\RealmOverview;
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
                image: '/images/characters/blade-knight.png',
                level: 400,
                resets: 20,
                grandResets: 7,
                online: true,
            ),
            new CharacterRankingEntry(
                name: 'iwebbg',
                className: 'Soul Master',
                image: '/images/characters/soul-master.jpg',
                level: 400,
                resets: 20,
                grandResets: 5,
                online: true,
            ),
            new CharacterRankingEntry(
                name: 'EvilDeviL',
                className: 'Magic Gladiator',
                image: '/images/characters/magic-gladiator.png',
                level: 400,
                resets: 19,
                grandResets: 5,
                online: false,
            ),
            new CharacterRankingEntry(
                name: 'Athena',
                className: 'Muse Elf',
                image: '/images/characters/muse-elf.png',
                level: 390,
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

        return array_slice($entries, 0, $this->normalizeLimit($limit));
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
                map: 'Devias',
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
                map: 'Arena',
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
