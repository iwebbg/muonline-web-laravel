<?php

namespace App\Game\Repositories;

use App\Game\Contracts\ZenBankRepository;
use App\Game\GameAccountWriteStatus;
use App\ZenBank\Exceptions\ZenBankRejected;
use App\ZenBank\ZenBankBlockReason;
use App\ZenBank\ZenProjectionResult;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use stdClass;

/**
 * Projects replay-safe Zen transfers into the Darksteam 97d+99i warehouse.
 */
final readonly class SqlServer97d99iZenBankRepository implements ZenBankRepository
{
    /**
     * Creates the repository with the configured game database manager.
     */
    public function __construct(private DatabaseManager $database) {}

    /**
     * Returns the current warehouse Zen balance without mutating game data.
     */
    public function currentBalance(string $accountId): ?int
    {
        $money = $this->database
            ->connection('game')
            ->table('warehouse')
            ->where('AccountID', $accountId)
            ->value('Money');

        return $money === null ? null : (int) $money;
    }

    /**
     * Applies or recognizes one exact before-and-after warehouse balance transition.
     */
    public function project(
        string $accountId,
        int $expectedBalance,
        int $resultingBalance,
    ): ZenProjectionResult {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use (
            $connection,
            $accountId,
            $expectedBalance,
            $resultingBalance,
        ): ZenProjectionResult {
            $this->assertOffline($connection, $accountId);
            $warehouse = $connection
                ->table('warehouse')
                ->where('AccountID', $accountId)
                ->lockForUpdate()
                ->first(['Money']);

            if (! $warehouse instanceof stdClass) {
                throw new ZenBankRejected(ZenBankBlockReason::WarehouseNotFound);
            }

            $currentBalance = (int) $warehouse->Money;

            if ($currentBalance === $resultingBalance) {
                return ZenProjectionResult::AlreadyApplied;
            }

            if ($currentBalance !== $expectedBalance) {
                return ZenProjectionResult::Conflict;
            }

            $connection
                ->table('warehouse')
                ->where('AccountID', $accountId)
                ->update(['Money' => $resultingBalance]);

            return ZenProjectionResult::Applied;
        }, attempts: 3);
    }

    /**
     * Rejects bank writes while the owning game account is connected.
     */
    private function assertOffline(Connection $connection, string $accountId): void
    {
        $status = $connection
            ->table('MEMB_STAT')
            ->where('memb___id', $accountId)
            ->lockForUpdate()
            ->first(['ConnectStat']);

        if (GameAccountWriteStatus::blocksWrites($status->ConnectStat ?? null)) {
            throw new ZenBankRejected(ZenBankBlockReason::CharacterOnline);
        }
    }
}
