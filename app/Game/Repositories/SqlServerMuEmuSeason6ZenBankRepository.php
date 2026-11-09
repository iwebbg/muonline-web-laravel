<?php

namespace App\Game\Repositories;

use App\Game\Contracts\ZenBankRepository;
use App\Game\GameAccountWriteStatus;
use App\ZenBank\Exceptions\ZenBankRejected;
use App\ZenBank\ZenBankBlockReason;
use App\ZenBank\ZenProjectionResult;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use stdClass;

/**
 * Projects replay-safe Zen transfers into the MuEMU Season 6 warehouse.
 */
final readonly class SqlServerMuEmuSeason6ZenBankRepository implements ZenBankRepository
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
        $connection = $this->database->connection('game');
        $vault = $this->activeVault($connection, $accountId);
        $money = $this->warehouseQuery($connection, $accountId, $vault)
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
            $vault = $this->activeVault($connection, $accountId, lock: true);
            $warehouse = $this->warehouseQuery($connection, $accountId, $vault)
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

            $this->warehouseQuery($connection, $accountId, $vault)
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

    /**
     * Resolves the active MuEMU warehouse page for reads or locked writes.
     */
    private function activeVault(
        Connection $connection,
        string $accountId,
        bool $lock = false,
    ): int {
        $query = $connection
            ->table('AccountCharacter')
            ->where('Id', $accountId);

        if ($lock) {
            $query->lockForUpdate();
        }

        return max(0, (int) ($query->value('ExtWarehouse') ?? 0));
    }

    /**
     * Returns a query for one standard or extended MuEMU warehouse page.
     */
    private function warehouseQuery(
        Connection $connection,
        string $accountId,
        int $vault,
    ): Builder {
        if ($vault === 0) {
            return $connection
                ->table('Warehouse')
                ->where('AccountID', $accountId);
        }

        return $connection
            ->table('ExtWarehouse')
            ->where('AccountID', $accountId)
            ->where('Number', $vault);
    }
}
