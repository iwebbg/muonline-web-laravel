<?php

namespace App\Game\Repositories;

use App\Game\Contracts\OnlineAccountRepository;
use App\Game\Data\OnlineAccountSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;

/**
 * Reads current DarksTeam login sessions without depending on historical rows.
 */
final readonly class SqlServerOnlineAccountRepository implements OnlineAccountRepository
{
    /**
     * Creates the reader with the isolated game database manager.
     */
    public function __construct(
        private DatabaseManager $database,
    ) {}

    /**
     * Returns connected accounts with the login timestamp that identifies the session.
     *
     * @return list<OnlineAccountSession>
     */
    public function onlineSessions(): array
    {
        $rows = $this->database->connection('game')
            ->table('MEMB_STAT')
            ->where('ConnectStat', '>', 0)
            ->whereNotNull('ConnectTM')
            ->orderBy('memb___id')
            ->get(['memb___id', 'ConnectTM']);
        $sessions = [];

        foreach ($rows as $row) {
            $sessions[] = new OnlineAccountSession(
                accountId: (string) $row->memb___id,
                connectedAt: CarbonImmutable::parse((string) $row->ConnectTM),
            );
        }

        return $sessions;
    }
}
