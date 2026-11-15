<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Game\Contracts\ItemStorageRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21ItemStorageRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\MuDevsSeason21\MuDevsSeason21Profile;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Exercises private native S21 storage without exposing locked or reserved cells.
 */
class MuDevsSeason21ItemStorageTest extends TestCase
{
    private const FLAMEBERGE = '1A00FF01020306000000FFFEFEFEFFFFFFFFFFFFFFFFFFFFFF';

    /**
     * Builds the native storage projection inside the network-isolated game database.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(GameVersionProfile::class, MuDevsSeason21Profile::class);
        $this->app->bind(ItemStorageRepository::class, SqlServerMuDevsSeason21ItemStorageRepository::class);
        $this->game()->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));
        $this->game()->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-web-columns.sql')));
    }

    /**
     * Returns native source geometry and only the unlocked inventory and warehouse area.
     */
    public function test_reads_owned_equipment_unlocked_bag_and_active_extended_vault(): void
    {
        $this->game()->table('Character')->insert([
            'AccountID' => 'owner', 'Name' => 'Hero', 'CharacterIndex' => 42,
            'Inventory' => $this->blob(239, [0, 12 + 70, 12 + 110, 236]), 'ExtInventory' => 1,
        ]);
        $this->game()->table('AccountCharacter')->insert(['AccountIndex' => 1, 'Id' => 'owner', 'ExtWarehouse' => 2]);
        $this->game()->table('warehouse')->insert([
            'AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => $this->blob(240, [0, 130]), 'Money' => 100,
        ]);
        $this->game()->table('ExtWarehouse')->insert([
            'AccountID' => 'owner', 'AccountIndex' => 1, 'Number' => 2, 'Items' => $this->blob(240, [7]), 'Money' => 250,
        ]);

        $token = 'native-storage';
        $response = $this->actingAs($this->account())->withSession(['_token' => $token])
            ->postJson('/account/item-storage', ['_token' => $token, 'characterName' => 'Hero']);

        $response->assertOk()
            ->assertJsonPath('storage.character.inventorySlotCount', 96)
            ->assertJsonCount(2, 'storage.character.equipment')
            ->assertJsonPath('storage.character.equipment.1.slot', 236)
            ->assertJsonPath('storage.character.equipmentSlotNumbers.14', 238)
            ->assertJsonCount(1, 'storage.character.inventory')
            ->assertJsonPath('storage.character.inventory.0.slot', 70)
            ->assertJsonPath('storage.character.inventory.0.name', 'Flameberge')
            ->assertJsonPath('storage.character.inventory.0.height', 4)
            ->assertJsonCount(1, 'storage.warehouse.vaults.0.items')
            ->assertJsonPath('storage.warehouse.vaults.1.number', 2)
            ->assertJsonPath('storage.warehouse.vaults.1.active', true)
            ->assertJsonPath('storage.warehouse.zen', 250)
            ->assertJsonMissingPath('storage.character.inventory.0.serial')
            ->assertJsonMissingPath('storage.character.inventory.0.nativeHex');
        $this->assertNotEmpty($response->json('storage.character.inventory.0.nativePresentation.details'));
    }

    /**
     * Keeps foreign character inventories and warehouses private at the HTTP boundary.
     */
    public function test_foreign_character_does_not_reveal_either_accounts_items(): void
    {
        $this->game()->table('Character')->insert([
            'AccountID' => 'other', 'Name' => 'Hidden', 'CharacterIndex' => 43,
            'Inventory' => $this->blob(239, [0]), 'ExtInventory' => 5,
        ]);
        $token = 'native-storage';

        $this->actingAs($this->account())->withSession(['_token' => $token])
            ->postJson('/account/item-storage', ['_token' => $token, 'characterName' => 'Hidden'])
            ->assertNotFound();
    }

    /**
     * Supports a warehouse-only account without requiring a character or active-page row.
     */
    public function test_account_without_characters_can_read_its_warehouse(): void
    {
        $this->game()->table('warehouse')->insert([
            'AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => $this->blob(240, [5]), 'Money' => 100,
        ]);
        $storage = $this->app->make(ItemStorageRepository::class)->forAccount('owner');

        $this->assertSame('', $storage->character->characterName);
        $this->assertCount(1, $storage->vaults);
        $this->assertSame(5, $storage->vaults[0]->items[0]->slot);
        $this->assertSame(100, $storage->warehouseZen);
    }

    /**
     * Requires login before returning native storage.
     */
    public function test_guest_cannot_read_storage(): void
    {
        $this->postJson('/account/item-storage')->assertUnauthorized();
    }

    /**
     * Builds exact-width native storage from one client-matched ordinary socket item.
     *
     * @param  list<int>  $occupiedSlots
     */
    private function blob(int $slotCount, array $occupiedSlots): string
    {
        $binary = str_repeat("\xFF", $slotCount * 25);

        foreach ($occupiedSlots as $slot) {
            $binary = substr_replace($binary, hex2bin(self::FLAMEBERGE), $slot * 25, 25);
        }

        return $binary;
    }

    /**
     * Returns one authenticated native game identity without local user persistence.
     */
    private function account(): GameAccount
    {
        return new GameAccount(1, 'owner', 'Owner', 'owner@example.test', false, false, 'secret');
    }

    /**
     * Returns the isolated native game connection.
     */
    private function game(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
