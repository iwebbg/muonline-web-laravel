<?php

namespace App\Game\Repositories;

use App\Game\Contracts\MarketItemEscrowRepository;
use App\Game\Data\ItemSlot;
use App\Game\GameAccountWriteStatus;
use App\Game\Versions\MuEmuSeason6\ItemCatalogMuEmuSeason6;
use App\Game\Versions\MuEmuSeason6\ItemCodecMuEmuSeason6;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketEscrowResult;
use App\Market\MarketListingBlockReason;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use stdClass;
use UnexpectedValueException;

/**
 * Moves original item bytes between MuEMU Season 6 warehouses and application escrow.
 */
final readonly class SqlServerMuEmuSeason6MarketItemEscrowRepository implements MarketItemEscrowRepository
{
    private const USABLE_WAREHOUSE_SLOTS = 120;

    private const STORED_WAREHOUSE_SLOTS = 240;

    private const WAREHOUSE_WIDTH = 8;

    /**
     * Creates the repository with the game database and verified item metadata.
     */
    public function __construct(
        private DatabaseManager $database,
        private ItemCodecMuEmuSeason6 $codec,
        private ItemCatalogMuEmuSeason6 $catalog,
    ) {}

    /**
     * Removes the exact expected item from one offline account warehouse slot.
     */
    public function extract(
        string $accountId,
        int $vault,
        int $slot,
        string $expectedBinary,
    ): MarketEscrowResult {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use (
            $connection,
            $accountId,
            $vault,
            $slot,
            $expectedBinary,
        ): MarketEscrowResult {
            $this->assertOffline($connection, $accountId);
            $warehouse = $this->lockedWarehouse($connection, $accountId, $vault);
            $binary = $this->normalizeBinary($warehouse->Items)
                ?? $this->emptyWarehouse();

            if ($slot < 0 || $slot >= self::USABLE_WAREHOUSE_SLOTS) {
                throw new MarketListingRejected(MarketListingBlockReason::ItemNotFound);
            }

            $currentBinary = substr(
                $binary,
                $slot * ItemCodecMuEmuSeason6::SLOT_BYTES,
                ItemCodecMuEmuSeason6::SLOT_BYTES,
            );

            if ($currentBinary === $this->emptySlot()) {
                return MarketEscrowResult::AlreadyExtracted;
            }

            if (! hash_equals($expectedBinary, $currentBinary)) {
                throw new MarketListingRejected(MarketListingBlockReason::ItemChanged);
            }

            $updatedBinary = substr_replace(
                $binary,
                $this->emptySlot(),
                $slot * ItemCodecMuEmuSeason6::SLOT_BYTES,
                ItemCodecMuEmuSeason6::SLOT_BYTES,
            );
            $this->updateWarehouseBinary(
                connection: $connection,
                accountId: $accountId,
                vault: $vault,
                binary: $updatedBinary,
            );

            return MarketEscrowResult::Extracted;
        }, attempts: 3);
    }

    /**
     * Deposits an escrowed item into the first free active-vault rectangle.
     */
    public function deposit(
        string $accountId,
        string $itemBinary,
        int $width,
        int $height,
    ): MarketEscrowResult {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use (
            $connection,
            $accountId,
            $itemBinary,
            $width,
            $height,
        ): MarketEscrowResult {
            $this->assertOffline($connection, $accountId);
            $vault = $this->activeVault($connection, $accountId);
            $warehouse = $this->lockedWarehouse($connection, $accountId, $vault);
            $binary = $this->normalizeBinary($warehouse->Items)
                ?? $this->emptyWarehouse();
            $visibleBinary = substr(
                $binary,
                0,
                self::USABLE_WAREHOUSE_SLOTS * ItemCodecMuEmuSeason6::SLOT_BYTES,
            );
            $slots = $this->codec->decode($visibleBinary, self::USABLE_WAREHOUSE_SLOTS);

            foreach ($slots as $slot) {
                if (! $slot->isEmpty() && hash_equals($itemBinary, $slot->binary())) {
                    return MarketEscrowResult::AlreadyDeposited;
                }
            }

            $occupied = $this->occupiedGrid($slots);
            $freeSlot = $this->firstFreeSlot($occupied, $width, $height);

            if ($freeSlot === null) {
                throw new MarketListingRejected(MarketListingBlockReason::StorageFull);
            }

            $updatedBinary = substr_replace(
                $binary,
                $itemBinary,
                $freeSlot * ItemCodecMuEmuSeason6::SLOT_BYTES,
                ItemCodecMuEmuSeason6::SLOT_BYTES,
            );
            $this->updateWarehouseBinary(
                connection: $connection,
                accountId: $accountId,
                vault: $vault,
                binary: $updatedBinary,
            );

            return MarketEscrowResult::Deposited;
        }, attempts: 3);
    }

    /**
     * Rejects escrow writes while the owning account is connected.
     */
    private function assertOffline(Connection $connection, string $accountId): void
    {
        $status = $connection
            ->table('MEMB_STAT')
            ->where('memb___id', $accountId)
            ->lockForUpdate()
            ->first(['ConnectStat']);

        if (GameAccountWriteStatus::blocksWrites($status->ConnectStat ?? null)) {
            throw new MarketListingRejected(MarketListingBlockReason::CharacterOnline);
        }
    }

    /**
     * Resolves the account's active MuEMU warehouse page.
     */
    private function activeVault(Connection $connection, string $accountId): int
    {
        $account = $connection
            ->table('AccountCharacter')
            ->where('Id', $accountId)
            ->lockForUpdate()
            ->first(['ExtWarehouse']);

        return max(0, (int) ($account->ExtWarehouse ?? 0));
    }

    /**
     * Returns one locked standard or extended warehouse row.
     */
    private function lockedWarehouse(
        Connection $connection,
        string $accountId,
        int $vault,
    ): stdClass {
        $query = $vault === 0
            ? $connection->table('Warehouse')->where('AccountID', $accountId)
            : $connection->table('ExtWarehouse')
                ->where('AccountID', $accountId)
                ->where('Number', $vault);
        $warehouse = $query
            ->lockForUpdate()
            ->first(['Items']);

        if (! $warehouse instanceof stdClass) {
            throw new MarketListingRejected(MarketListingBlockReason::WarehouseNotFound);
        }

        return $warehouse;
    }

    /**
     * Returns a query for one standard or extended warehouse page.
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

    /**
     * Builds occupied cells from every decoded top-left item and catalog dimension.
     *
     * @param  list<ItemSlot>  $slots
     * @return array<int, bool>
     */
    private function occupiedGrid(array $slots): array
    {
        $occupied = array_fill(0, self::USABLE_WAREHOUSE_SLOTS, false);

        foreach ($slots as $slot) {
            if ($slot->isEmpty()) {
                continue;
            }

            $definition = $this->catalog->definitionFor(
                (int) $slot->group,
                (int) $slot->index,
            );
            $width = $definition?->width ?? 1;
            $height = $definition?->height ?? 1;
            $originColumn = $slot->slot % self::WAREHOUSE_WIDTH;
            $originRow = intdiv($slot->slot, self::WAREHOUSE_WIDTH);

            for ($row = 0; $row < $height; $row++) {
                for ($column = 0; $column < $width; $column++) {
                    $cellColumn = $originColumn + $column;
                    $cellRow = $originRow + $row;

                    if ($cellColumn >= self::WAREHOUSE_WIDTH || $cellRow >= 15) {
                        continue;
                    }

                    $occupied[($cellRow * self::WAREHOUSE_WIDTH) + $cellColumn] = true;
                }
            }
        }

        return $occupied;
    }

    /**
     * Finds the first rectangle that can hold the returned item.
     *
     * @param  array<int, bool>  $occupied
     */
    private function firstFreeSlot(array $occupied, int $width, int $height): ?int
    {
        if ($width < 1 || $height < 1 || $width > self::WAREHOUSE_WIDTH || $height > 15) {
            return null;
        }

        for ($row = 0; $row <= 15 - $height; $row++) {
            for ($column = 0; $column <= self::WAREHOUSE_WIDTH - $width; $column++) {
                $fits = true;

                for ($itemRow = 0; $itemRow < $height; $itemRow++) {
                    for ($itemColumn = 0; $itemColumn < $width; $itemColumn++) {
                        $slot = (($row + $itemRow) * self::WAREHOUSE_WIDTH) + $column + $itemColumn;

                        if ($occupied[$slot]) {
                            $fits = false;
                            break 2;
                        }
                    }
                }

                if ($fits) {
                    return ($row * self::WAREHOUSE_WIDTH) + $column;
                }
            }
        }

        return null;
    }

    /**
     * Writes arbitrary item bytes without asking pdo_sqlsrv to encode them as UCS-2 text.
     */
    private function updateWarehouseBinary(
        Connection $connection,
        string $accountId,
        int $vault,
        string $binary,
    ): void {
        if ($connection->getDriverName() !== 'sqlsrv') {
            $this->warehouseQuery($connection, $accountId, $vault)
                ->update(['Items' => $binary]);

            return;
        }

        $grammar = $connection->getQueryGrammar();
        $warehouse = $grammar->wrapTable($vault === 0 ? 'Warehouse' : 'ExtWarehouse');
        $column = $grammar->wrap('Items');
        $accountColumn = $grammar->wrap('AccountID');
        $statement = "UPDATE {$warehouse} SET {$column} = CONVERT(varbinary(max), ?, 2) WHERE {$accountColumn} = ?";
        $bindings = [strtoupper(bin2hex($binary)), $accountId];

        if ($vault !== 0) {
            $numberColumn = $grammar->wrap('Number');
            $statement .= " AND {$numberColumn} = ?";
            $bindings[] = $vault;
        }

        $connection->update($statement, $bindings);
    }

    /**
     * Normalizes SQL Server varbinary values returned as strings or streams.
     */
    private function normalizeBinary(mixed $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        if (is_resource($value)) {
            $binary = stream_get_contents($value);

            if ($binary !== false) {
                return $binary;
            }
        }

        throw new UnexpectedValueException('The game database returned an unsupported binary value.');
    }

    /**
     * Returns one empty MuEMU Season 6 item slot.
     */
    private function emptySlot(): string
    {
        return str_repeat("\xFF", ItemCodecMuEmuSeason6::SLOT_BYTES);
    }

    /**
     * Returns one empty native 240-slot blob while only 120 cells are player-visible.
     */
    private function emptyWarehouse(): string
    {
        return str_repeat($this->emptySlot(), self::STORED_WAREHOUSE_SLOTS);
    }
}
