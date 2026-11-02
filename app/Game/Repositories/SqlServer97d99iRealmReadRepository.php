<?php

namespace App\Game\Repositories;

use App\Game\Contracts\RealmReadRepository;
use App\Game\Data\CharacterRankingEntry;
use App\Game\Data\GuildRankingEntry;
use App\Game\Data\PublicCharacterProfile;
use App\Game\Data\PublicGuildMember;
use App\Game\Data\PublicGuildProfile;
use App\Game\Data\RealmOverview;
use App\Game\Versions\Version97d99i\CharacterClassMapper;
use App\Game\Versions\Version97d99i\CharacterImageMapper;
use App\Game\Versions\Version97d99i\MapNameMapper;
use Illuminate\Database\DatabaseManager;
use stdClass;

/**
 * Reads normalized public data from the legacy MuOnline 97d+99i schema.
 */
final class SqlServer97d99iRealmReadRepository implements RealmReadRepository
{
    /**
     * Creates the repository with the Laravel database manager and class mapper.
     */
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly CharacterClassMapper $classMapper,
        private readonly CharacterImageMapper $imageMapper,
        private readonly MapNameMapper $mapNameMapper,
    ) {}

    /**
     * Returns aggregate realm counts using only SELECT queries.
     */
    public function overview(): RealmOverview
    {
        $connection = $this->database->connection('game');
        $onlineAccounts = $connection
            ->table('MEMB_STAT')
            ->where('ConnectStat', 1)
            ->count();
        $accounts = $connection
            ->table('MEMB_INFO')
            ->count();
        $characters = $connection
            ->table('Character')
            ->count();
        $guilds = $connection
            ->table('Guild')
            ->count();

        return new RealmOverview(
            name: (string) config('game.profile.name'),
            version: (string) config('game.profile.version'),
            experience: (string) config('game.profile.experience'),
            drop: (string) config('game.profile.drop'),
            online: $onlineAccounts,
            capacity: (int) config('game.profile.capacity'),
            accounts: $accounts,
            characters: $characters,
            guilds: $guilds,
            dataSource: 'database',
        );
    }

    /**
     * Returns characters ordered by grand resets, resets and current level.
     *
     * @return list<CharacterRankingEntry>
     */
    public function topCharacters(int $limit): array
    {
        $connection = $this->database->connection($this->rankingConnectionName());
        $normalizedLimit = $this->normalizeLimit($limit);
        $rows = $connection
            ->table('Character as characters')
            ->leftJoin('MEMB_STAT as account_status', 'characters.AccountID', '=', 'account_status.memb___id')
            ->select([
                'characters.Name as name',
                'characters.Class as class_code',
                'characters.Resets as resets',
                'characters.GrandResets as grand_resets',
                'account_status.ConnectStat as connect_stat',
            ])
            ->orderByDesc('characters.GrandResets')
            ->orderByDesc('characters.Resets')
            ->orderByDesc('characters.cLevel')
            ->limit($normalizedLimit)
            ->get();
        $entries = [];

        foreach ($rows as $row) {
            $className = $this->classMapper->nameFor((int) $row->class_code);
            $isOnline = (int) ($row->connect_stat ?? 0) === 1;
            $entries[] = new CharacterRankingEntry(
                name: (string) $row->name,
                className: $className,
                resets: (int) $row->resets,
                grandResets: (int) $row->grand_resets,
                online: $isOnline,
            );
        }

        return $entries;
    }

    /**
     * Returns guilds ordered by their persisted 97d+99i guild score.
     *
     * @return list<GuildRankingEntry>
     */
    public function topGuilds(int $limit): array
    {
        $connection = $this->database->connection($this->rankingConnectionName());
        $normalizedLimit = $this->normalizeLimit($limit);
        $rows = $connection
            ->table('Guild')
            ->select([
                'G_Name as name',
                'G_Master as master',
                'G_Score as score',
                'G_Count as members',
            ])
            ->orderByDesc('G_Score')
            ->limit($normalizedLimit)
            ->get();
        $entries = [];

        foreach ($rows as $row) {
            $entries[] = new GuildRankingEntry(
                name: (string) $row->name,
                master: (string) $row->master,
                score: (int) $row->score,
                members: (int) $row->members,
            );
        }

        return $entries;
    }

    /**
     * Returns one character from the public ranking connection without account data.
     */
    public function publicCharacter(string $characterName): ?PublicCharacterProfile
    {
        $row = $this->database
            ->connection($this->rankingConnectionName())
            ->table('Character as characters')
            ->leftJoin('MEMB_STAT as account_status', 'characters.AccountID', '=', 'account_status.memb___id')
            ->leftJoin('GuildMember as guild_member', 'characters.Name', '=', 'guild_member.Name')
            ->where('characters.Name', $characterName)
            ->first([
                'characters.Name as name',
                'characters.cLevel as level',
                'characters.Class as class_code',
                'characters.Resets as resets',
                'characters.GrandResets as grand_resets',
                'characters.MapNumber as map_number',
                'characters.PkLevel as pk_level',
                'characters.IsVip as is_vip',
                'guild_member.G_Name as guild_name',
                'account_status.ConnectStat as connect_stat',
            ]);

        if (! $row instanceof stdClass) {
            return null;
        }

        $classCode = (int) $row->class_code;

        return new PublicCharacterProfile(
            name: (string) $row->name,
            className: $this->classMapper->nameFor($classCode),
            image: $this->imageMapper->pathFor($classCode),
            level: (int) $row->level,
            resets: (int) $row->resets,
            grandResets: (int) $row->grand_resets,
            map: $this->mapNameMapper->nameFor((int) $row->map_number),
            pkLevel: (int) $row->pk_level,
            guildName: is_string($row->guild_name) ? $row->guild_name : null,
            online: (int) ($row->connect_stat ?? 0) === 1,
            vip: (int) $row->is_vip === 1,
        );
    }

    /**
     * Returns one guild and roster from the public ranking connection.
     */
    public function publicGuild(string $guildName): ?PublicGuildProfile
    {
        $connection = $this->database->connection($this->rankingConnectionName());
        $guild = $connection
            ->table('Guild')
            ->where('G_Name', $guildName)
            ->first([
                'G_Name as name',
                'G_Master as master',
                'G_Score as score',
                'G_Notice as notice',
            ]);

        if (! $guild instanceof stdClass) {
            return null;
        }

        $rows = $connection
            ->table('GuildMember as guild_members')
            ->join('Character as characters', 'guild_members.Name', '=', 'characters.Name')
            ->leftJoin('MEMB_STAT as account_status', 'characters.AccountID', '=', 'account_status.memb___id')
            ->where('guild_members.G_Name', $guildName)
            ->orderByDesc('characters.GrandResets')
            ->orderByDesc('characters.Resets')
            ->orderBy('characters.Name')
            ->get([
                'characters.Name as name',
                'characters.Class as class_code',
                'characters.cLevel as level',
                'characters.Resets as resets',
                'characters.GrandResets as grand_resets',
                'account_status.ConnectStat as connect_stat',
            ]);
        $members = [];

        foreach ($rows as $row) {
            $members[] = new PublicGuildMember(
                name: (string) $row->name,
                className: $this->classMapper->nameFor((int) $row->class_code),
                level: (int) $row->level,
                resets: (int) $row->resets,
                grandResets: (int) $row->grand_resets,
                online: (int) ($row->connect_stat ?? 0) === 1,
                master: (string) $row->name === (string) $guild->master,
            );
        }

        return new PublicGuildProfile(
            name: (string) $guild->name,
            master: (string) $guild->master,
            score: (int) $guild->score,
            notice: (string) ($guild->notice ?? ''),
            members: $members,
        );
    }

    /**
     * Keeps public ranking requests inside a safe and predictable range.
     */
    private function normalizeLimit(int $limit): int
    {
        return max(1, min($limit, 100));
    }

    /**
     * Returns the profile-selected read-only connection used by ranking queries.
     */
    private function rankingConnectionName(): string
    {
        $connectionName = (string) config('game.database_connections.rankings', 'game');

        return $connectionName === '' ? 'game' : $connectionName;
    }
}
