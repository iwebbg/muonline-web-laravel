<?php

namespace App\Game\Repositories;

use App\Game\Contracts\BattleCoreReadRepository;
use App\Game\Data\BattleCoreAccountSnapshot;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;
use Psr\Log\LoggerInterface;

/**
 * Reads the separate native BattleCore database through account and source-realm boundaries.
 */
final readonly class SqlServerMuDevsSeason21BattleCoreReadRepository implements BattleCoreReadRepository
{
    /**
     * Uses the optional read-only connection and a password-safe availability logger.
     */
    public function __construct(
        private DatabaseManager $database,
        private LoggerInterface $logger,
    ) {}

    /**
     * Reads recorded registration states and unclaimed quantities from the configured realms.
     *
     * WZ_BattleCore_CancelToJoin establishes RegisterState 1 as registered and
     * 0 as cancelled/not registered. Reward procedures select TookState 0 before
     * marking claimed rows; this reader never calls those mutating procedures.
     */
    public function forAccount(string $accountId): ?BattleCoreAccountSnapshot
    {
        if (! (bool) config('game.battle_core.reads_enabled', false)) {
            return null;
        }

        $serverCodes = (array) config('game.battle_core.server_codes', []);

        if ($serverCodes === []) {
            return new BattleCoreAccountSnapshot(false, [], 0);
        }

        try {
            $connection = $this->database->connection((string) config('game.database_connections.battle_core', 'battle_core'));
            $rows = $connection->table('BattleCoreUser')
                ->where('AccountID', $accountId)
                ->whereIn('ServerCode', $serverCodes)
                ->orderBy('Name')
                ->orderBy('ServerCode')
                ->limit(100)
                ->get(['Name', 'RegisterState', 'RegisterMonth', 'RegisterDay']);
            $unclaimedItems = (int) $connection->table('BattleCoreReward')
                ->where('AccountID', $accountId)
                ->whereIn('ServerCode', $serverCodes)
                ->where('TookState', 0)
                ->where('ItemCount', '>', 0)
                ->sum('ItemCount');
        } catch (QueryException $exception) {
            $this->logger->warning('The optional Battle Core account read is unavailable.', [
                'exception_class' => $exception::class,
            ]);

            return new BattleCoreAccountSnapshot(false, [], 0);
        }

        $registrations = [];

        foreach ($rows as $row) {
            $state = $row->RegisterState === null ? null : (int) $row->RegisterState;
            $registrations[] = [
                'name' => (string) $row->Name,
                'registered' => match ($state) {
                    0 => false,
                    1 => true,
                    default => null,
                },
                'month' => (int) $row->RegisterMonth,
                'day' => (int) $row->RegisterDay,
            ];
        }

        return new BattleCoreAccountSnapshot(true, $registrations, $unclaimedItems);
    }
}
