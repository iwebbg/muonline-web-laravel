<?php

namespace App\Game\Repositories;

use App\Game\Contracts\OperationAwareCharacterInventoryEscrowRepository;
use App\Game\Data\ExpectedInventoryItem;
use App\Game\Data\ItemSlot;
use App\Game\GameAccountWriteStatus;
use App\Game\Versions\MuDevsSeason21\ItemCatalogAdapterMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\ItemCodecAdapterMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\ItemOperationJournalMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\ItemTransferRulesMuDevsSeason21;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketEscrowResult;
use App\Market\MarketListingBlockReason;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use LogicException;
use stdClass;

/**
 * Moves exact item bytes between MUDevs Season 21 character bags and application custody.
 */
final readonly class SqlServerMuDevsSeason21CharacterInventoryEscrowRepository implements OperationAwareCharacterInventoryEscrowRepository
{
    private const EQUIPMENT_SLOTS = 12;

    private const BASE_INVENTORY_SLOTS = 64;

    private const INVENTORY_EXTENSION_SLOTS = 32;

    private const MAXIMUM_INVENTORY_EXTENSIONS = 5;

    private const STORED_CHARACTER_SLOTS = 239;

    private const INVENTORY_WIDTH = 8;

    /**
     * Creates the adapter with the legacy game database and item metadata.
     */
    public function __construct(
        private DatabaseManager $database,
        private ItemCodecAdapterMuDevsSeason21 $codec,
        private ItemCatalogAdapterMuDevsSeason21 $catalog,
        private ItemTransferRulesMuDevsSeason21 $transferRules,
        private ItemOperationJournalMuDevsSeason21 $journal,
    ) {}

    /**
     * Requires a stable operation identity for native inventory extraction.
     */
    public function extract(
        string $accountId,
        string $characterName,
        int $slot,
        string $expectedBinary,
    ): MarketEscrowResult {
        throw new LogicException('Native S21 custody requires an operation identity.');
    }

    /**
     * Requires a stable operation identity for native inventory batch extraction.
     *
     * @param  list<ExpectedInventoryItem>  $expectedItems
     */
    public function extractMany(
        string $accountId,
        string $characterName,
        array $expectedItems,
    ): MarketEscrowResult {
        throw new LogicException('Native S21 custody requires an operation identity.');
    }

    /**
     * Requires a stable operation identity for native inventory delivery.
     */
    public function deposit(
        string $accountId,
        string $characterName,
        string $itemBinary,
        int $width,
        int $height,
    ): MarketEscrowResult {
        throw new LogicException('Native S21 custody requires an operation identity.');
    }

    /**
     * Commits one exact owned bag extraction and its receipt atomically.
     */
    public function extractForOperation(
        string $accountId,
        string $characterName,
        int $slot,
        string $expectedBinary,
        string $operationKey,
    ): MarketEscrowResult {
        $payloadHash = hash('sha256', serialize([$accountId, $characterName, $slot, $expectedBinary]));

        return $this->journal->run(
            connection: $this->database->connection('game'), operationKey: $operationKey,
            accountId: $accountId, direction: 'inventory-extract', payloadHash: $payloadHash,
            appliedResult: MarketEscrowResult::Extracted,
            apply: fn (): MarketEscrowResult => $this->extractNative($accountId, $characterName, $slot, $expectedBinary),
        );
    }

    /**
     * Records a complete native bag batch once, regardless of input ordering.
     *
     * @param  list<ExpectedInventoryItem>  $expectedItems
     */
    public function extractManyForOperation(
        string $accountId,
        string $characterName,
        array $expectedItems,
        string $operationKey,
    ): MarketEscrowResult {
        $canonicalItems = [];

        foreach ($expectedItems as $item) {
            if (! $item instanceof ExpectedInventoryItem) {
                throw new MarketListingRejected(MarketListingBlockReason::ItemNotFound);
            }

            $canonicalItems[] = ['slot' => $item->slot, 'binary' => $item->binary];
        }

        usort($canonicalItems, static fn (array $left, array $right): int => $left['slot'] <=> $right['slot']);
        $payloadHash = hash('sha256', serialize([$accountId, $characterName, $canonicalItems]));

        return $this->journal->run(
            connection: $this->database->connection('game'), operationKey: $operationKey,
            accountId: $accountId, direction: 'inventory-batch-extract', payloadHash: $payloadHash,
            appliedResult: MarketEscrowResult::Extracted,
            apply: fn (): MarketEscrowResult => $this->extractManyNative($accountId, $characterName, $expectedItems),
        );
    }

    /**
     * Commits native bag delivery and its receipt in one game transaction.
     */
    public function depositForOperation(
        string $accountId,
        string $characterName,
        string $itemBinary,
        int $width,
        int $height,
        string $operationKey,
    ): MarketEscrowResult {
        $payloadHash = hash('sha256', serialize([$accountId, $characterName, $itemBinary, $width, $height]));

        return $this->journal->run(
            connection: $this->database->connection('game'), operationKey: $operationKey,
            accountId: $accountId, direction: 'inventory-deposit', payloadHash: $payloadHash,
            appliedResult: MarketEscrowResult::Deposited,
            apply: fn (): MarketEscrowResult => $this->depositNative($accountId, $characterName, $itemBinary, $width, $height),
        );
    }

    /**
     * Removes the exact expected item from one owned offline character bag slot.
     */
    private function extractNative(
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
            $this->transferRules->assertTransferable($expectedBinary);
            $character = $this->lockedCharacter($connection, $accountId, $characterName);
            $inventory = $this->normalizeBinary($character->Inventory)
                ?? $this->emptyInventory();
            $inventorySlots = $this->availableInventorySlots($character);

            if ($slot < 0 || $slot >= $inventorySlots) {
                throw new MarketListingRejected(MarketListingBlockReason::ItemNotFound);
            }

            $binaryOffset = (self::EQUIPMENT_SLOTS + $slot) * ItemCodecAdapterMuDevsSeason21::SLOT_BYTES;
            $currentBinary = substr(
                $inventory,
                $binaryOffset,
                ItemCodecAdapterMuDevsSeason21::SLOT_BYTES,
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
                ItemCodecAdapterMuDevsSeason21::SLOT_BYTES,
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
    private function extractManyNative(
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
            $inventorySlots = $this->availableInventorySlots($character);
            $emptyItems = 0;
            $seenSlots = [];

            foreach ($expectedItems as $expectedItem) {
                if (
                    ! $expectedItem instanceof ExpectedInventoryItem
                    || $expectedItem->slot < 0
                    || $expectedItem->slot >= $inventorySlots
                    || isset($seenSlots[$expectedItem->slot])
                ) {
                    throw new MarketListingRejected(MarketListingBlockReason::ItemNotFound);
                }

                $this->transferRules->assertTransferable($expectedItem->binary);
                $seenSlots[$expectedItem->slot] = true;
                $binaryOffset = (self::EQUIPMENT_SLOTS + $expectedItem->slot) * ItemCodecAdapterMuDevsSeason21::SLOT_BYTES;
                $currentBinary = substr($inventory, $binaryOffset, ItemCodecAdapterMuDevsSeason21::SLOT_BYTES);

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
                $binaryOffset = (self::EQUIPMENT_SLOTS + $expectedItem->slot) * ItemCodecAdapterMuDevsSeason21::SLOT_BYTES;
                $inventory = substr_replace($inventory, $this->emptySlot(), $binaryOffset, ItemCodecAdapterMuDevsSeason21::SLOT_BYTES);
            }

            $this->updateInventory($connection, $accountId, $characterName, $inventory);

            return MarketEscrowResult::Extracted;
        }, attempts: 3);
    }

    /**
     * Deposits one escrowed item into the first free character-bag rectangle.
     */
    private function depositNative(
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
            $item = $this->transferRules->assertTransferable($itemBinary);

            if ($item->width !== $width || $item->height !== $height) {
                throw new MarketListingRejected(MarketListingBlockReason::UnsupportedItem);
            }
            $character = $this->lockedCharacter($connection, $accountId, $characterName);
            $inventory = $this->normalizeBinary($character->Inventory)
                ?? $this->emptyInventory();
            $inventorySlots = $this->availableInventorySlots($character);
            $activeInventory = substr(
                $inventory,
                self::EQUIPMENT_SLOTS * ItemCodecAdapterMuDevsSeason21::SLOT_BYTES,
                $inventorySlots * ItemCodecAdapterMuDevsSeason21::SLOT_BYTES,
            );
            $bagSlots = $this->codec->decode($activeInventory, $inventorySlots);

            foreach ($bagSlots as $slot) {
                if (! $slot->isEmpty() && hash_equals($itemBinary, $slot->binary())) {
                    return MarketEscrowResult::AlreadyDeposited;
                }
            }

            $occupied = $this->occupiedGrid($bagSlots, $inventorySlots);
            $freeSlot = $this->firstFreeSlot($occupied, $inventorySlots, $width, $height);

            if ($freeSlot === null) {
                throw new MarketListingRejected(MarketListingBlockReason::StorageFull);
            }

            $binaryOffset = (self::EQUIPMENT_SLOTS + $freeSlot) * ItemCodecAdapterMuDevsSeason21::SLOT_BYTES;
            $updatedInventory = substr_replace(
                $inventory,
                $itemBinary,
                $binaryOffset,
                ItemCodecAdapterMuDevsSeason21::SLOT_BYTES,
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
    private function assertOffline(
        Connection $connection,
        string $accountId,
    ): void {
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
            ->first(['Inventory', 'ExtInventory']);

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
    private function occupiedGrid(
        array $slots,
        int $inventorySlots,
    ): array {
        $occupied = array_fill(0, $inventorySlots, false);
        $inventoryRows = intdiv($inventorySlots, self::INVENTORY_WIDTH);

        foreach ($slots as $slot) {
            if ($slot->isEmpty()) {
                continue;
            }

            $definition = $this->catalog->definitionFor(
                (int) $slot->group,
                (int) $slot->index,
            );
            if ($definition === null) {
                throw new MarketListingRejected(MarketListingBlockReason::UnsupportedItem);
            }

            $width = $definition->width;
            $height = $definition->height;
            $originColumn = $slot->slot % self::INVENTORY_WIDTH;
            $originRow = intdiv($slot->slot, self::INVENTORY_WIDTH);

            for ($row = 0; $row < $height; $row++) {
                for ($column = 0; $column < $width; $column++) {
                    $cellColumn = $originColumn + $column;
                    $cellRow = $originRow + $row;

                    if ($cellColumn >= self::INVENTORY_WIDTH || $cellRow >= $inventoryRows) {
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
    private function firstFreeSlot(
        array $occupied,
        int $inventorySlots,
        int $width,
        int $height,
    ): ?int {
        $inventoryRows = intdiv($inventorySlots, self::INVENTORY_WIDTH);

        if ($width < 1 || $height < 1 || $width > self::INVENTORY_WIDTH || $height > $inventoryRows) {
            return null;
        }

        for ($row = 0; $row <= $inventoryRows - $height; $row++) {
            $pageEndRow = $row < 8 ? 8 : 8 + ((intdiv($row - 8, 4) + 1) * 4);

            if ($row + $height > $pageEndRow) {
                continue;
            }

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
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || strlen($value) !== self::STORED_CHARACTER_SLOTS * ItemCodecAdapterMuDevsSeason21::SLOT_BYTES) {
            throw new MarketListingRejected(MarketListingBlockReason::UnsupportedItem);
        }

        return $value;
    }

    /**
     * Returns one empty MUDevs Season 21 item slot.
     */
    private function emptySlot(): string
    {
        return str_repeat("\xFF", ItemCodecAdapterMuDevsSeason21::SLOT_BYTES);
    }

    /**
     * Returns one empty equipment-and-bag inventory blob.
     */
    private function emptyInventory(): string
    {
        return str_repeat($this->emptySlot(), self::STORED_CHARACTER_SLOTS);
    }

    /**
     * Returns only player-accessible bag slots unlocked by Character.ExtInventory.
     */
    private function availableInventorySlots(
        stdClass $character,
    ): int {
        $extensions = max(
            0,
            min(self::MAXIMUM_INVENTORY_EXTENSIONS, (int) $character->ExtInventory),
        );

        return self::BASE_INVENTORY_SLOTS + ($extensions * self::INVENTORY_EXTENSION_SLOTS);
    }
}
