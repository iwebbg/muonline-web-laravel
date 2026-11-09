<?php

namespace App\Game\Repositories;

use App\Game\Contracts\ItemStorageRepository;
use App\Game\Data\AccountItemStorage;
use App\Game\Data\CharacterItemStorage;
use App\Game\Data\ItemSlot;
use App\Game\Data\WarehouseVault;
use App\Game\Versions\MuEmuSeason6\ItemCatalogMuEmuSeason6;
use App\Game\Versions\MuEmuSeason6\ItemCodecMuEmuSeason6;
use Illuminate\Database\DatabaseManager;
use stdClass;
use UnexpectedValueException;

/**
 * Reads MuEMU Season 6 character inventory and extended warehouse pages.
 */
final readonly class SqlServerMuEmuSeason6ItemStorageRepository implements ItemStorageRepository
{
    private const STORED_CHARACTER_SLOTS = 236;

    private const EQUIPMENT_SLOTS = 12;

    private const BASE_INVENTORY_SLOTS = 64;

    private const INVENTORY_EXTENSION_SLOTS = 32;

    private const MAXIMUM_INVENTORY_EXTENSIONS = 2;

    private const USABLE_WAREHOUSE_SLOTS = 120;

    private const STORED_WAREHOUSE_SLOTS = 240;

    /**
     * Creates the repository with the game database and version codec.
     */
    public function __construct(
        private DatabaseManager $database,
        private ItemCodecMuEmuSeason6 $codec,
        private ItemCatalogMuEmuSeason6 $catalog,
    ) {}

    /**
     * Returns account-scoped warehouse data for accounts without characters.
     */
    public function forAccount(string $accountId): AccountItemStorage
    {
        $emptyCharacterStorage = new CharacterItemStorage(
            characterName: '',
            equipment: [],
            inventory: [],
        );

        return $this->withWarehouse(
            accountId: $accountId,
            characterStorage: $emptyCharacterStorage,
        );
    }

    /**
     * Returns character and warehouse items after enforcing account ownership.
     */
    public function forAccountAndCharacter(
        string $accountId,
        string $characterName,
    ): ?AccountItemStorage {
        $connection = $this->database->connection('game');
        $character = $connection
            ->table('Character')
            ->where('AccountID', $accountId)
            ->where('Name', $characterName)
            ->first(['Name', 'Inventory', 'ExtInventory']);

        if ($character === null) {
            return null;
        }

        $inventoryBinary = $this->normalizeBinary($character->Inventory)
            ?? $this->emptyBlob(self::STORED_CHARACTER_SLOTS);
        $inventorySlots = self::BASE_INVENTORY_SLOTS + (
            max(0, min(self::MAXIMUM_INVENTORY_EXTENSIONS, (int) $character->ExtInventory))
            * self::INVENTORY_EXTENSION_SLOTS
        );
        $activeBinary = substr(
            $inventoryBinary,
            0,
            (self::EQUIPMENT_SLOTS + $inventorySlots) * ItemCodecMuEmuSeason6::SLOT_BYTES,
        );
        $characterSlots = $this->codec->decode(
            $activeBinary,
            self::EQUIPMENT_SLOTS + $inventorySlots,
        );
        $equipment = $this->occupiedSlots(array_slice($characterSlots, 0, self::EQUIPMENT_SLOTS));
        $bagSlots = array_slice($characterSlots, self::EQUIPMENT_SLOTS);
        $inventory = $this->occupiedSlots($bagSlots, self::EQUIPMENT_SLOTS);
        $characterStorage = new CharacterItemStorage(
            characterName: (string) $character->Name,
            equipment: $equipment,
            inventory: $inventory,
        );

        return $this->withWarehouse(
            accountId: $accountId,
            characterStorage: $characterStorage,
        );
    }

    /**
     * Combines decoded character storage with its account-owned warehouses.
     */
    private function withWarehouse(
        string $accountId,
        CharacterItemStorage $characterStorage,
    ): AccountItemStorage {
        $connection = $this->database->connection('game');
        $warehouse = $connection
            ->table('Warehouse')
            ->where('AccountID', $accountId)
            ->first(['AccountID', 'Items', 'Money']);
        $activeVault = (int) ($connection
            ->table('AccountCharacter')
            ->where('Id', $accountId)
            ->value('ExtWarehouse') ?? 0);
        $extendedWarehouses = $connection
            ->table('ExtWarehouse')
            ->where('AccountID', $accountId)
            ->orderBy('Number')
            ->get(['Items', 'Money', 'Number']);

        if ($warehouse === null && $extendedWarehouses->isEmpty()) {
            return new AccountItemStorage(
                character: $characterStorage,
                warehouseZen: 0,
                vaults: [],
            );
        }

        $vaults = [];
        $warehouseZen = 0;

        if ($warehouse instanceof stdClass) {
            $vaults[] = $this->mapVault(0, $activeVault === 0, $warehouse->Items);

            if ($activeVault === 0) {
                $warehouseZen = (int) $warehouse->Money;
            }
        }

        foreach ($extendedWarehouses as $extendedWarehouse) {
            $vaultNumber = (int) $extendedWarehouse->Number;
            $isActive = $vaultNumber === $activeVault;
            $vaults[] = $this->mapVault(
                $vaultNumber,
                $isActive,
                $extendedWarehouse->Items,
            );

            if ($isActive) {
                $warehouseZen = (int) $extendedWarehouse->Money;
            }
        }

        return new AccountItemStorage(
            character: $characterStorage,
            warehouseZen: $warehouseZen,
            vaults: $vaults,
        );
    }

    /**
     * Decodes the 120 player-visible cells while retaining MuEMU's 240-slot blob.
     */
    private function mapVault(int $number, bool $active, mixed $items): WarehouseVault
    {
        $storedBinary = $this->normalizeBinary($items)
            ?? $this->emptyBlob(self::STORED_WAREHOUSE_SLOTS);
        $visibleBinary = substr(
            $storedBinary,
            0,
            self::USABLE_WAREHOUSE_SLOTS * ItemCodecMuEmuSeason6::SLOT_BYTES,
        );
        $slots = $this->codec->decode($visibleBinary, self::USABLE_WAREHOUSE_SLOTS);

        return new WarehouseVault(
            number: $number,
            active: $active,
            items: $this->occupiedSlots($slots),
        );
    }

    /**
     * Removes empty slots and optionally rebases their UI positions.
     *
     * @param  list<ItemSlot>  $slots
     * @return list<ItemSlot>
     */
    private function occupiedSlots(array $slots, int $slotOffset = 0): array
    {
        $occupied = [];

        foreach ($slots as $slot) {
            if ($slot->isEmpty()) {
                continue;
            }

            $item = $slotOffset > 0
                ? $slot->withSlot($slot->slot - $slotOffset)
                : $slot;
            $definition = $this->catalog->definitionFor(
                (int) $item->group,
                (int) $item->index,
            );

            $occupied[] = $definition === null
                ? $item
                : $item->withDefinition($definition);
        }

        return $occupied;
    }

    /**
     * Normalizes SQL Server varbinary values returned as strings or streams.
     */
    private function normalizeBinary(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
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
     * Creates an empty fixed-size item blob for nullable legacy storage fields.
     */
    private function emptyBlob(int $slots): string
    {
        return str_repeat("\xFF", $slots * ItemCodecMuEmuSeason6::SLOT_BYTES);
    }
}
