<?php

namespace App\Game\Repositories;

use App\Game\Contracts\RealmReadRepository;
use App\Game\Data\CharacterRankingEntry;
use App\Game\Data\GuildRankingEntry;
use App\Game\Data\RealmOverview;
use App\Game\Versions\Version97d99i\CharacterClassMapper;
use Illuminate\Database\DatabaseManager;

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
