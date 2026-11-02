<?php

namespace App\Game\Repositories;

use App\Game\Contracts\MarketItemEscrowRepository;
use App\Game\Data\ItemSlot;
use App\Game\Versions\Version97d99i\ItemCatalog97d99i;
use App\Game\Versions\Version97d99i\ItemCodec97d99i;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketEscrowResult;
use App\Market\MarketListingBlockReason;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use stdClass;
use UnexpectedValueException;

/**
 * Moves original item bytes between Darksteam warehouses and application escrow.
 */
final readonly class SqlServer97d99iMarketItemEscrowRepository implements MarketItemEscrowRepository
{
    private const WAREHOUSE_SLOTS = 120;

    private const WAREHOUSE_WIDTH = 8;

    private const MAXIMUM_MULTI_VAULT_INDEX = 10;

    /**
     * Creates the repository with the game database and verified item metadata.
     */
    public function __construct(
        private DatabaseManager $database,
        private ItemCodec97d99i $codec,
        private ItemCatalog97d99i $catalog,
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
            $columns = $this->warehouseColumns($connection);
            $warehouse = $this->lockedWarehouse($connection, $accountId, $columns);
            $storageColumn = $this->storageColumn($warehouse, $columns, $vault);
            $binary = $this->normalizeBinary($warehouse->{$storageColumn})
                ?? $this->emptyWarehouse();

            if ($slot < 0 || $slot >= self::WAREHOUSE_SLOTS) {
                throw new MarketListingRejected(MarketListingBlockReason::ItemNotFound);
            }

            $currentBinary = substr(
                $binary,
                $slot * ItemCodec97d99i::SLOT_BYTES,
                ItemCodec97d99i::SLOT_BYTES,
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
                $slot * ItemCodec97d99i::SLOT_BYTES,
                ItemCodec97d99i::SLOT_BYTES,
            );
            $connection
                ->table('warehouse')
                ->where('AccountID', $accountId)
                ->update([$storageColumn => $updatedBinary]);

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
            $columns = $this->warehouseColumns($connection);
            $warehouse = $this->lockedWarehouse($connection, $accountId, $columns);
            $binary = $this->normalizeBinary($warehouse->Items)
                ?? $this->emptyWarehouse();
            $slots = $this->codec->decode($binary, self::WAREHOUSE_SLOTS);

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
                $freeSlot * ItemCodec97d99i::SLOT_BYTES,
                ItemCodec97d99i::SLOT_BYTES,
            );
            $connection
                ->table('warehouse')
                ->where('AccountID', $accountId)
                ->update(['Items' => $updatedBinary]);

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

        if ((int) ($status->ConnectStat ?? 0) === 1) {
            throw new MarketListingRejected(MarketListingBlockReason::CharacterOnline);
        }
    }

    /**
     * Returns the locked warehouse row or rejects accounts without storage.
     *
     * @param  list<string>  $columns
     */
    private function lockedWarehouse(
        Connection $connection,
        string $accountId,
        array $columns,
    ): stdClass {
        $warehouse = $connection
            ->table('warehouse')
            ->where('AccountID', $accountId)
            ->lockForUpdate()
            ->first($columns);

        if (! $warehouse instanceof stdClass) {
            throw new MarketListingRejected(MarketListingBlockReason::WarehouseNotFound);
        }

        return $warehouse;
    }

    /**
     * Lists standard and detected MultiVault columns for the locked row.
     *
     * @return list<string>
     */
    private function warehouseColumns(Connection $connection): array
    {
        $availableColumns = array_map(
            static fn (string $column): string => strtolower($column),
            $connection->getSchemaBuilder()->getColumnListing('warehouse'),
        );
        $columns = ['AccountID', 'Items'];

        if (! in_array('multivault', $availableColumns, true)) {
            return $columns;
        }

        $columns[] = 'MultiVault';

        for ($vault = 0; $vault <= self::MAXIMUM_MULTI_VAULT_INDEX; $vault++) {
            $column = sprintf('Items%02d', $vault);

            if (in_array(strtolower($column), $availableColumns, true)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * Resolves one selected vault to its actual mutable database column.
     *
     * @param  list<string>  $columns
     */
    private function storageColumn(stdClass $warehouse, array $columns, int $vault): string
    {
        if (! in_array('MultiVault', $columns, true)) {
            if ($vault === 0) {
                return 'Items';
            }

            throw new MarketListingRejected(MarketListingBlockReason::WarehouseNotFound);
        }

        if ((int) $warehouse->MultiVault === $vault) {
            return 'Items';
        }

        $column = sprintf('Items%02d', $vault);

        if (! in_array($column, $columns, true)) {
            throw new MarketListingRejected(MarketListingBlockReason::WarehouseNotFound);
        }

        return $column;
    }

    /**
     * Builds occupied cells from every decoded top-left item and catalog dimension.
     *
     * @param  list<ItemSlot>  $slots
     * @return array<int, bool>
     */
    private function occupiedGrid(array $slots): array
    {
        $occupied = array_fill(0, self::WAREHOUSE_SLOTS, false);

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
     * Returns one empty 97d+99i item slot.
     */
    private function emptySlot(): string
    {
        return str_repeat("\xFF", ItemCodec97d99i::SLOT_BYTES);
    }

    /**
     * Returns one empty 120-slot warehouse blob.
     */
    private function emptyWarehouse(): string
    {
        return str_repeat($this->emptySlot(), self::WAREHOUSE_SLOTS);
    }
}
