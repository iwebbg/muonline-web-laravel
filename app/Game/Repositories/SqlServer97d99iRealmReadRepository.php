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
use App\Game\Data\RealmOverview;
use App\Game\Rankings\RankingBoard;
use App\Game\Versions\Version97d99i\CharacterClassFilter;
use App\Game\Versions\Version97d99i\CharacterClassMapper;
use App\Game\Versions\Version97d99i\CharacterImageMapper;
use App\Game\Versions\Version97d99i\GuildMarkDecoder;
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
        private readonly GuildMarkDecoder $guildMarkDecoder,
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
        $normalizedLimit = $this->normalizeLimit($limit);
        $page = $this->characterRankings(new CharacterRankingQuery(
            board: RankingBoard::Overall,
            classFilter: null,
            page: 1,
            perPage: $normalizedLimit,
            search: '',
            onlineOnly: false,
        ));

        return $page->entries;
    }

    /**
     * Returns one character page filtered and ordered entirely by SQL Server.
     */
    public function characterRankings(CharacterRankingQuery $query): CharacterRankingPage
    {
        $connection = $this->database->connection($this->rankingConnectionName());
        $builder = $connection
            ->table('Character as characters')
            ->leftJoin('MEMB_STAT as account_status', 'characters.AccountID', '=', 'account_status.memb___id');

        if ($query->classFilter !== null) {
            $builder->where('characters.Class', $query->classFilter->classCode());
        }

        if ($query->search !== '') {
            $builder->where('characters.Name', 'like', '%'.$query->search.'%');
        }

        if ($query->onlineOnly) {
            $builder->where('account_status.ConnectStat', 1);
        }

        $total = (int) (clone $builder)->count('characters.Name');

        if ($query->board === RankingBoard::PlayerKillers) {
            $builder
                ->orderByDesc('characters.PkCount')
                ->orderByDesc('characters.PkLevel');
        } else {
            $builder
                ->orderByDesc('characters.GrandResets')
                ->orderByDesc('characters.Resets')
                ->orderByDesc('characters.cLevel');
        }

        $rows = $builder
            ->orderBy('characters.Name')
            ->offset(($query->page - 1) * $query->perPage)
            ->limit($query->perPage)
            ->get([
                'characters.Name as name',
                'characters.Class as class_code',
                'characters.cLevel as level',
                'characters.Resets as resets',
                'characters.GrandResets as grand_resets',
                'characters.PkCount as pk_count',
                'characters.PkLevel as pk_level',
                'account_status.ConnectStat as connect_stat',
            ]);
        $entries = [];

        foreach ($rows as $row) {
            $entries[] = $this->characterRankingEntry($row);
        }

        return new CharacterRankingPage(
            entries: $entries,
            total: $total,
            page: $query->page,
            perPage: $query->perPage,
            lastPage: max(1, (int) ceil($total / $query->perPage)),
        );
    }

    /**
     * Returns guilds ordered by their persisted 97d+99i guild score.
     *
     * @return list<GuildRankingEntry>
     */
    public function topGuilds(int $limit): array
    {
        $normalizedLimit = $this->normalizeLimit($limit);
        $page = $this->guildRankings(new GuildRankingQuery(
            page: 1,
            perPage: $normalizedLimit,
            search: '',
        ));

        return $page->entries;
    }

    /**
     * Returns one guild page searched and ordered entirely by SQL Server.
     */
    public function guildRankings(GuildRankingQuery $query): GuildRankingPage
    {
        $connection = $this->database->connection($this->rankingConnectionName());
        $builder = $connection->table('Guild');

        if ($query->search !== '') {
            $builder->where(function ($searchBuilder) use ($query): void {
                $searchBuilder
                    ->where('G_Name', 'like', '%'.$query->search.'%')
                    ->orWhere('G_Master', 'like', '%'.$query->search.'%');
            });
        }

        $total = (int) (clone $builder)->count('G_Name');
        $rows = $builder
            ->orderByDesc('G_Score')
            ->orderBy('G_Name')
            ->offset(($query->page - 1) * $query->perPage)
            ->limit($query->perPage)
            ->get([
                'G_Name as name',
                'G_Master as master',
                'G_Score as score',
                'G_Count as members',
                'G_Mark as mark',
            ]);
        $entries = [];

        foreach ($rows as $row) {
            $entries[] = $this->guildRankingEntry($row);
        }

        return new GuildRankingPage(
            entries: $entries,
            total: $total,
            page: $query->page,
            perPage: $query->perPage,
            lastPage: max(1, (int) ceil($total / $query->perPage)),
        );
    }

    /**
     * Maps one legacy character row into the version-neutral ranking entry.
     */
    private function characterRankingEntry(stdClass $row): CharacterRankingEntry
    {
        $classCode = (int) $row->class_code;
        $classFilter = CharacterClassFilter::fromClassCode($classCode);

        return new CharacterRankingEntry(
            name: (string) $row->name,
            classCode: $classCode,
            className: $this->classMapper->nameFor($classCode),
            classAbbreviation: $classFilter?->abbreviation() ?? '??',
            image: $this->imageMapper->pathFor($classCode),
            level: (int) $row->level,
            resets: (int) $row->resets,
            grandResets: (int) $row->grand_resets,
            pkCount: (int) $row->pk_count,
            pkLevel: (int) $row->pk_level,
            online: (int) ($row->connect_stat ?? 0) === 1,
        );
    }

    /**
     * Maps one legacy guild row into the version-neutral ranking entry.
     */
    private function guildRankingEntry(stdClass $row): GuildRankingEntry
    {
        return new GuildRankingEntry(
            name: (string) $row->name,
            master: (string) $row->master,
            score: (int) $row->score,
            members: (int) $row->members,
            markColors: $this->guildMarkDecoder->colors(
                is_string($row->mark) ? $row->mark : '',
            ),
        );
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
                'G_Mark as mark',
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
            markColors: $this->guildMarkDecoder->colors(
                is_string($guild->mark) ? $guild->mark : '',
            ),
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
