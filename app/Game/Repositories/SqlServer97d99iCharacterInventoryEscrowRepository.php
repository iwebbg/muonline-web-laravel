<?php

namespace App\Game\Repositories;

use App\Game\Contracts\CharacterInventoryEscrowRepository;
use App\Game\Data\ExpectedInventoryItem;
use App\Game\Data\ItemSlot;
use App\Game\GameAccountWriteStatus;
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
 * Moves exact item bytes between Darksteam character bags and application custody.
 */
final readonly class SqlServer97d99iCharacterInventoryEscrowRepository implements CharacterInventoryEscrowRepository
{
    private const EQUIPMENT_SLOTS = 12;

    private const INVENTORY_SLOTS = 64;

    private const INVENTORY_WIDTH = 8;

    /**
     * Creates the adapter with the legacy game database and item metadata.
     */
    public function __construct(
        private DatabaseManager $database,
        private ItemCodec97d99i $codec,
        private ItemCatalog97d99i $catalog,
    ) {}

    /**
     * Removes the exact expected item from one owned offline character bag slot.
     */
    public function extract(
        string $accountId,
        string $characterName,
        int $slot,
        string $expectedBinary,
    ): MarketEscrowResult {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use (
            $connection,
            $accountId,
            $characterName,
            $slot,
            $expectedBinary,
        ): MarketEscrowResult {
            $this->assertOffline($connection, $accountId);
            $character = $this->lockedCharacter($connection, $accountId, $characterName);
            $inventory = $this->normalizeBinary($character->Inventory)
                ?? $this->emptyInventory();

            if ($slot < 0 || $slot >= self::INVENTORY_SLOTS) {
                throw new MarketListingRejected(MarketListingBlockReason::ItemNotFound);
            }

            $binaryOffset = (self::EQUIPMENT_SLOTS + $slot) * ItemCodec97d99i::SLOT_BYTES;
            $currentBinary = substr(
                $inventory,
                $binaryOffset,
                ItemCodec97d99i::SLOT_BYTES,
            );

            if ($currentBinary === $this->emptySlot()) {
                return MarketEscrowResult::AlreadyExtracted;
            }

            if (! hash_equals($expectedBinary, $currentBinary)) {
                throw new MarketListingRejected(MarketListingBlockReason::ItemChanged);
            }

            $updatedInventory = substr_replace(
                $inventory,
                $this->emptySlot(),
                $binaryOffset,
                ItemCodec97d99i::SLOT_BYTES,
            );
            $this->updateInventory(
                connection: $connection,
                accountId: $accountId,
                characterName: $characterName,
                inventory: $updatedInventory,
            );

            return MarketEscrowResult::Extracted;
        }, attempts: 3);
    }

    /**
     * Removes an exact item batch in one locked inventory write.
     *
     * @param  list<ExpectedInventoryItem>  $expectedItems
     */
    public function extractMany(
        string $accountId,
        string $characterName,
        array $expectedItems,
    ): MarketEscrowResult {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use (
            $connection,
            $accountId,
            $characterName,
            $expectedItems,
        ): MarketEscrowResult {
            $this->assertOffline($connection, $accountId);
            $character = $this->lockedCharacter($connection, $accountId, $characterName);
            $inventory = $this->normalizeBinary($character->Inventory) ?? $this->emptyInventory();
            $emptyItems = 0;
            $seenSlots = [];

            foreach ($expectedItems as $expectedItem) {
                if (
                    ! $expectedItem instanceof ExpectedInventoryItem
                    || $expectedItem->slot < 0
                    || $expectedItem->slot >= self::INVENTORY_SLOTS
                    || isset($seenSlots[$expectedItem->slot])
                ) {
                    throw new MarketListingRejected(MarketListingBlockReason::ItemNotFound);
                }

                $seenSlots[$expectedItem->slot] = true;
                $binaryOffset = (self::EQUIPMENT_SLOTS + $expectedItem->slot) * ItemCodec97d99i::SLOT_BYTES;
                $currentBinary = substr($inventory, $binaryOffset, ItemCodec97d99i::SLOT_BYTES);

                if ($currentBinary === $this->emptySlot()) {
                    $emptyItems++;

                    continue;
                }

                if (! hash_equals($expectedItem->binary, $currentBinary)) {
                    throw new MarketListingRejected(MarketListingBlockReason::ItemChanged);
                }
            }

            if ($emptyItems === count($expectedItems)) {
                return MarketEscrowResult::AlreadyExtracted;
            }

            if ($emptyItems > 0) {
                throw new MarketListingRejected(MarketListingBlockReason::ItemChanged);
            }

            foreach ($expectedItems as $expectedItem) {
                $binaryOffset = (self::EQUIPMENT_SLOTS + $expectedItem->slot) * ItemCodec97d99i::SLOT_BYTES;
                $inventory = substr_replace($inventory, $this->emptySlot(), $binaryOffset, ItemCodec97d99i::SLOT_BYTES);
            }

            $this->updateInventory($connection, $accountId, $characterName, $inventory);

            return MarketEscrowResult::Extracted;
        }, attempts: 3);
    }

    /**
     * Deposits one escrowed item into the first free character-bag rectangle.
     */
    public function deposit(
        string $accountId,
        string $characterName,
        string $itemBinary,
        int $width,
        int $height,
    ): MarketEscrowResult {
        $connection = $this->database->connection('game');

        return $connection->transaction(function () use (
            $connection,
            $accountId,
            $characterName,
            $itemBinary,
            $width,
            $height,
        ): MarketEscrowResult {
            $this->assertOffline($connection, $accountId);
            $character = $this->lockedCharacter($connection, $accountId, $characterName);
            $inventory = $this->normalizeBinary($character->Inventory)
                ?? $this->emptyInventory();
            $allSlots = $this->codec->decode(
                $inventory,
                self::EQUIPMENT_SLOTS + self::INVENTORY_SLOTS,
            );
            $bagSlots = array_slice($allSlots, self::EQUIPMENT_SLOTS);

            foreach ($bagSlots as $slot) {
                if (! $slot->isEmpty() && hash_equals($itemBinary, $slot->binary())) {
                    return MarketEscrowResult::AlreadyDeposited;
                }
            }

            $occupied = $this->occupiedGrid($bagSlots);
            $freeSlot = $this->firstFreeSlot($occupied, $width, $height);

            if ($freeSlot === null) {
                throw new MarketListingRejected(MarketListingBlockReason::StorageFull);
            }

            $binaryOffset = (self::EQUIPMENT_SLOTS + $freeSlot) * ItemCodec97d99i::SLOT_BYTES;
            $updatedInventory = substr_replace(
                $inventory,
                $itemBinary,
                $binaryOffset,
                ItemCodec97d99i::SLOT_BYTES,
            );
            $this->updateInventory(
                connection: $connection,
                accountId: $accountId,
                characterName: $characterName,
                inventory: $updatedInventory,
            );

            return MarketEscrowResult::Deposited;
        }, attempts: 3);
    }

    /**
     * Rejects mutations while any character on the account is connected.
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
     * Returns the locked owned character row required for one inventory mutation.
     */
    private function lockedCharacter(
        Connection $connection,
        string $accountId,
        string $characterName,
    ): stdClass {
        $character = $connection
            ->table('Character')
            ->where('AccountID', $accountId)
            ->where('Name', $characterName)
            ->lockForUpdate()
            ->first(['Inventory']);

        if (! $character instanceof stdClass) {
            throw new MarketListingRejected(MarketListingBlockReason::CharacterNotFound);
        }

        return $character;
    }

    /**
     * Builds occupied bag cells from decoded top-left items and catalog dimensions.
     *
     * @param  list<ItemSlot>  $slots
     * @return array<int, bool>
     */
    private function occupiedGrid(array $slots): array
    {
        $occupied = array_fill(0, self::INVENTORY_SLOTS, false);

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
            $originColumn = $slot->slot % self::INVENTORY_WIDTH;
            $originRow = intdiv($slot->slot, self::INVENTORY_WIDTH);

            for ($row = 0; $row < $height; $row++) {
                for ($column = 0; $column < $width; $column++) {
                    $cellColumn = $originColumn + $column;
                    $cellRow = $originRow + $row;

                    if ($cellColumn >= self::INVENTORY_WIDTH || $cellRow >= 8) {
                        continue;
                    }

                    $occupied[($cellRow * self::INVENTORY_WIDTH) + $cellColumn] = true;
                }
            }
        }

        return $occupied;
    }

    /**
     * Finds the first free bag rectangle that can contain the returned item.
     *
     * @param  array<int, bool>  $occupied
     */
    private function firstFreeSlot(array $occupied, int $width, int $height): ?int
    {
        if ($width < 1 || $height < 1 || $width > self::INVENTORY_WIDTH || $height > 8) {
            return null;
        }

        for ($row = 0; $row <= 8 - $height; $row++) {
            for ($column = 0; $column <= self::INVENTORY_WIDTH - $width; $column++) {
                $fits = true;

                for ($itemRow = 0; $itemRow < $height; $itemRow++) {
                    for ($itemColumn = 0; $itemColumn < $width; $itemColumn++) {
                        $slot = (($row + $itemRow) * self::INVENTORY_WIDTH) + $column + $itemColumn;

                        if ($occupied[$slot]) {
                            $fits = false;
                            break 2;
                        }
                    }
                }

                if ($fits) {
                    return ($row * self::INVENTORY_WIDTH) + $column;
                }
            }
        }

        return null;
    }

    /**
     * Writes binary inventory bytes without SQL Server UCS-2 conversion.
     */
    private function updateInventory(
        Connection $connection,
        string $accountId,
        string $characterName,
        string $inventory,
    ): void {
        if ($connection->getDriverName() !== 'sqlsrv') {
            $connection
                ->table('Character')
                ->where('AccountID', $accountId)
                ->where('Name', $characterName)
                ->update(['Inventory' => $inventory]);

            return;
        }

        $grammar = $connection->getQueryGrammar();
        $table = $grammar->wrapTable('Character');
        $inventoryColumn = $grammar->wrap('Inventory');
        $accountColumn = $grammar->wrap('AccountID');
        $nameColumn = $grammar->wrap('Name');
        $statement = "UPDATE {$table} SET {$inventoryColumn} = CONVERT(varbinary(max), ?, 2) WHERE {$accountColumn} = ? AND {$nameColumn} = ?";

        $connection->update($statement, [
            strtoupper(bin2hex($inventory)),
            $accountId,
            $characterName,
        ]);
    }

    /**
     * Normalizes SQL Server varbinary strings and streams.
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

        throw new UnexpectedValueException('The game database returned an unsupported inventory value.');
    }

    /**
     * Returns one empty 97d+99i item slot.
     */
    private function emptySlot(): string
    {
        return str_repeat("\xFF", ItemCodec97d99i::SLOT_BYTES);
    }

    /**
     * Returns one empty equipment-and-bag inventory blob.
     */
    private function emptyInventory(): string
    {
        return str_repeat(
            $this->emptySlot(),
            self::EQUIPMENT_SLOTS + self::INVENTORY_SLOTS,
        );
    }
}
