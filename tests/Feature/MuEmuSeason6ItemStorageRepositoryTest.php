<?php

namespace Tests\Feature;

use App\Game\Repositories\SqlServerMuEmuSeason6CharacterInventoryEscrowRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6ItemStorageRepository;
use App\Game\Versions\MuEmuSeason6\ItemCatalogMuEmuSeason6;
use App\Game\Versions\MuEmuSeason6\ItemCodecMuEmuSeason6;
use App\Market\MarketEscrowResult;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Verifies MuEMU storage boundaries without exposing reserved native slots.
 */
final class MuEmuSeason6ItemStorageRepositoryTest extends TestCase
{
    private const SACRED_GLOVE = '20DD41FFFFFE3958000000FFFFFFFFFF';

    private const KRIS = '003F14FFFFFE3C0000005DFFFFFFFFFF';

    /**
     * Creates the consumed Update 32 item-storage schema.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.game', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $database = $this->app->make(DatabaseManager::class);

        $database->purge('game');

        $connection = $database->connection('game');

        $connection->statement('CREATE TABLE MEMB_STAT (memb___id TEXT PRIMARY KEY, ConnectStat INTEGER NULL)');
        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, Inventory BLOB NULL, ExtInventory INTEGER NOT NULL DEFAULT 0)');
        $connection->statement('CREATE TABLE Warehouse (AccountID TEXT PRIMARY KEY, Items BLOB NULL, Money INTEGER NULL)');
        $connection->statement('CREATE TABLE ExtWarehouse (AccountID TEXT NOT NULL, Items BLOB NULL, Money INTEGER NULL, Number INTEGER NULL)');
        $connection->statement('CREATE TABLE AccountCharacter (Id TEXT PRIMARY KEY, ExtWarehouse INTEGER NOT NULL DEFAULT 0)');
    }

    /**
     * Confirms unlocked bag cells and each native warehouse page are decoded safely.
     */
    public function test_reads_unlocked_inventory_and_extended_warehouse_pages(): void
    {
        $inventory = $this->emptySlots(236);
        $inventory = $this->putItem($inventory, 12 + 70, self::SACRED_GLOVE);
        $inventory = $this->putItem($inventory, 12 + 110, self::KRIS);
        $mainWarehouse = $this->putItem($this->emptySlots(240), 0, self::KRIS);
        $mainWarehouse = $this->putItem($mainWarehouse, 130, self::SACRED_GLOVE);
        $extendedWarehouse = $this->putItem($this->emptySlots(240), 1, self::SACRED_GLOVE);
        $connection = $this->game();

        $connection->table('Character')->insert([
            'AccountID' => 'owner',
            'Name' => 'Fighter',
            'Inventory' => $inventory,
            'ExtInventory' => 1,
        ]);
        $connection->table('Warehouse')->insert([
            'AccountID' => 'owner',
            'Items' => $mainWarehouse,
            'Money' => 10_000,
        ]);
        $connection->table('ExtWarehouse')->insert([
            'AccountID' => 'owner',
            'Items' => $extendedWarehouse,
            'Money' => 25_000,
            'Number' => 1,
        ]);
        $connection->table('AccountCharacter')->insert([
            'Id' => 'owner',
            'ExtWarehouse' => 1,
        ]);

        $storage = $this->storageRepository()->forAccountAndCharacter('owner', 'Fighter');

        self::assertNotNull($storage);
        self::assertCount(1, $storage->character->inventory);
        self::assertSame(70, $storage->character->inventory[0]->slot);
        self::assertSame('Sacred Glove', $storage->character->inventory[0]->name);
        self::assertSame(25_000, $storage->warehouseZen);
        self::assertCount(2, $storage->vaults);
        self::assertFalse($storage->vaults[0]->active);
        self::assertCount(1, $storage->vaults[0]->items);
        self::assertTrue($storage->vaults[1]->active);
        self::assertSame('Sacred Glove', $storage->vaults[1]->items[0]->name);
    }

    /**
     * Confirms extraction changes one unlocked slot and preserves all reserved bytes.
     */
    public function test_extracts_from_unlocked_extension_without_truncating_inventory(): void
    {
        $expectedItem = hex2bin(self::SACRED_GLOVE);
        $reservedItem = hex2bin(self::KRIS);

        self::assertIsString($expectedItem);
        self::assertIsString($reservedItem);

        $inventory = $this->putItem($this->emptySlots(236), 12 + 70, self::SACRED_GLOVE);
        $inventory = $this->putItem($inventory, 235, self::KRIS);
        $connection = $this->game();

        $connection->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 0]);
        $connection->table('Character')->insert([
            'AccountID' => 'owner',
            'Name' => 'Fighter',
            'Inventory' => $inventory,
            'ExtInventory' => 1,
        ]);

        $result = $this->escrowRepository()->extract('owner', 'Fighter', 70, $expectedItem);
        $stored = $connection->table('Character')->where('Name', 'Fighter')->value('Inventory');

        self::assertSame(MarketEscrowResult::Extracted, $result);
        self::assertIsString($stored);
        self::assertSame(3_776, strlen($stored));
        self::assertSame(str_repeat("\xFF", 16), substr($stored, (12 + 70) * 16, 16));
        self::assertSame($reservedItem, substr($stored, 235 * 16, 16));
    }

    /**
     * Returns the read-only MuEMU storage adapter.
     */
    private function storageRepository(): SqlServerMuEmuSeason6ItemStorageRepository
    {
        return new SqlServerMuEmuSeason6ItemStorageRepository(
            $this->app->make(DatabaseManager::class),
            new ItemCodecMuEmuSeason6,
            new ItemCatalogMuEmuSeason6,
        );
    }

    /**
     * Returns the MuEMU character-bag custody adapter.
     */
    private function escrowRepository(): SqlServerMuEmuSeason6CharacterInventoryEscrowRepository
    {
        return new SqlServerMuEmuSeason6CharacterInventoryEscrowRepository(
            $this->app->make(DatabaseManager::class),
            new ItemCodecMuEmuSeason6,
            new ItemCatalogMuEmuSeason6,
        );
    }

    /**
     * Returns the isolated game connection.
     */
    private function game(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }

    /**
     * Builds an empty native item-slot sequence.
     */
    private function emptySlots(int $slots): string
    {
        return str_repeat("\xFF", $slots * ItemCodecMuEmuSeason6::SLOT_BYTES);
    }

    /**
     * Places one exact sixteen-byte item in a native storage slot.
     */
    private function putItem(string $storage, int $slot, string $hex): string
    {
        $item = hex2bin($hex);

        self::assertIsString($item);

        return substr_replace(
            $storage,
            $item,
            $slot * ItemCodecMuEmuSeason6::SLOT_BYTES,
            ItemCodecMuEmuSeason6::SLOT_BYTES,
        );
    }
}
