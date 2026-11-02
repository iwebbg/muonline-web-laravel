<?php

namespace App\Game\Repositories;

use App\Game\Contracts\ItemStorageRepository;
use App\Game\Data\AccountItemStorage;
use App\Game\Data\CharacterItemStorage;
use App\Game\Data\ItemSlot;
use App\Game\Data\WarehouseVault;
use App\Game\Versions\Version97d99i\ItemCatalog97d99i;
use App\Game\Versions\Version97d99i\ItemCodec97d99i;
use Illuminate\Database\DatabaseManager;
use stdClass;
use UnexpectedValueException;

/**
 * Reads 97d+99i character inventory and optional MultiVault warehouse columns.
 */
final readonly class SqlServer97d99iItemStorageRepository implements ItemStorageRepository
{
    private const CHARACTER_SLOTS = 76;

    private const EQUIPMENT_SLOTS = 12;

    private const WAREHOUSE_SLOTS = 120;

    private const MAXIMUM_MULTI_VAULT_INDEX = 10;

    /**
     * Creates the repository with the game database and version codec.
     */
    public function __construct(
        private DatabaseManager $database,
        private ItemCodec97d99i $codec,
        private ItemCatalog97d99i $catalog,
    ) {}

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
            ->first(['Name', 'Inventory']);

        if ($character === null) {
            return null;
        }

        $inventoryBinary = $this->normalizeBinary($character->Inventory)
            ?? $this->emptyBlob(self::CHARACTER_SLOTS);
        $characterSlots = $this->codec->decode($inventoryBinary, self::CHARACTER_SLOTS);
        $equipment = $this->occupiedSlots(array_slice($characterSlots, 0, self::EQUIPMENT_SLOTS));
        $bagSlots = array_slice($characterSlots, self::EQUIPMENT_SLOTS);
        $inventory = $this->occupiedSlots($bagSlots, self::EQUIPMENT_SLOTS);
        $characterStorage = new CharacterItemStorage(
            characterName: (string) $character->Name,
            equipment: $equipment,
            inventory: $inventory,
        );
        $warehouseColumns = $this->warehouseColumns();
        $warehouse = $connection
            ->table('warehouse')
            ->where('AccountID', $accountId)
            ->first($warehouseColumns);

        if ($warehouse === null) {
            return new AccountItemStorage(
                character: $characterStorage,
                warehouseZen: 0,
                vaults: [],
            );
        }

        return new AccountItemStorage(
            character: $characterStorage,
            warehouseZen: (int) $warehouse->Money,
            vaults: $this->mapVaults($warehouse, $warehouseColumns),
        );
    }

    /**
     * Lists standard and detected MultiVault columns without assuming an extended schema.
     *
     * @return list<string>
     */
    private function warehouseColumns(): array
    {
        $schema = $this->database->connection('game')->getSchemaBuilder();
        $availableColumns = array_map(
            static fn (string $column): string => strtolower($column),
            $schema->getColumnListing('warehouse'),
        );
        $columns = ['AccountID', 'Items', 'Money'];

        if (! in_array('multivault', $availableColumns, true)) {
            return $columns;
        }

        $columns[] = 'MultiVault';

        for ($vaultNumber = 0; $vaultNumber <= self::MAXIMUM_MULTI_VAULT_INDEX; $vaultNumber++) {
            $column = sprintf('Items%02d', $vaultNumber);

            if (in_array(strtolower($column), $availableColumns, true)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * Maps the active warehouse and every populated MultiVault snapshot.
     *
     * @param  list<string>  $selectedColumns
     * @return list<WarehouseVault>
     */
    private function mapVaults(stdClass $warehouse, array $selectedColumns): array
    {
        $supportsMultiVault = in_array('MultiVault', $selectedColumns, true);
        $activeVault = $supportsMultiVault
            ? max(0, min(self::MAXIMUM_MULTI_VAULT_INDEX, (int) $warehouse->MultiVault))
            : 0;
        $vaults = [];

        for ($vaultNumber = 0; $vaultNumber <= self::MAXIMUM_MULTI_VAULT_INDEX; $vaultNumber++) {
            $column = sprintf('Items%02d', $vaultNumber);
            $storedBinary = in_array($column, $selectedColumns, true)
                ? $this->normalizeBinary($warehouse->{$column})
                : null;
            $isActive = $vaultNumber === $activeVault;

            if (! $isActive && $storedBinary === null) {
                continue;
            }

            $vaultBinary = $isActive
                ? $this->normalizeBinary($warehouse->Items)
                : $storedBinary;
            $vaultBinary ??= $this->emptyBlob(self::WAREHOUSE_SLOTS);
            $slots = $this->codec->decode($vaultBinary, self::WAREHOUSE_SLOTS);
            $vaults[] = new WarehouseVault(
                number: $vaultNumber,
                active: $isActive,
                items: $this->occupiedSlots($slots),
            );

            if (! $supportsMultiVault) {
                break;
            }
        }

        return $vaults;
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
        return str_repeat("\xFF", $slots * ItemCodec97d99i::SLOT_BYTES);
    }
}
