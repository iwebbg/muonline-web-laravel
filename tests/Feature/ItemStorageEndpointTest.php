<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use Illuminate\Database\DatabaseManager;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Verifies authenticated ownership-scoped inventory and MultiVault reads.
 */
final class ItemStorageEndpointTest extends TestCase
{
    /**
     * Creates isolated legacy inventory and warehouse tables.
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

        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, Inventory BLOB NULL)');
        $connection->statement('CREATE TABLE warehouse (AccountID TEXT PRIMARY KEY, Items BLOB NULL, Money INTEGER NOT NULL, MultiVault INTEGER NULL, Items00 BLOB NULL, Items01 BLOB NULL, Items02 BLOB NULL)');
    }

    /**
     * Confirms equipment, bag and populated vaults decode for the owning account.
     */
    public function test_it_returns_owned_character_and_multivault_item_storage(): void
    {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $equipmentItem = $this->item('00842001020304050000');
        $bagItem = $this->item('1F5B2AAABBCCDDC1037E');
        $activeVaultItem = $this->item('20001200000000000000');
        $secondVaultItem = $this->item('40002011223344000000');

        $connection->table('Character')->insert([
            'AccountID' => 'owner',
            'Name' => 'OwnKnight',
            'Inventory' => $this->blob(76, [0 => $equipmentItem, 12 => $bagItem]),
        ]);
        $connection->table('warehouse')->insert([
            'AccountID' => 'owner',
            'Items' => $this->blob(120, [5 => $activeVaultItem]),
            'Money' => 30_000_000,
            'MultiVault' => 1,
            'Items00' => null,
            'Items01' => $this->blob(120),
            'Items02' => $this->blob(120, [7 => $secondVaultItem]),
        ]);

        $response = $this
            ->actingAs($this->account('owner'))
            ->postItemStorage('OwnKnight');

        $response->assertOk();
        $response->assertJsonPath('storage.character.characterName', 'OwnKnight');
        $response->assertJsonPath('storage.character.equipment.0.slot', 0);
        $response->assertJsonPath('storage.character.equipment.0.code', '0:0');
        $response->assertJsonPath('storage.character.inventory.0.slot', 0);
        $response->assertJsonPath('storage.character.inventory.0.code', '8:31');
        $response->assertJsonPath('storage.warehouse.zen', 30_000_000);
        $response->assertJsonPath('storage.warehouse.vaults.0.number', 1);
        $response->assertJsonPath('storage.warehouse.vaults.0.active', true);
        $response->assertJsonPath('storage.warehouse.vaults.0.items.0.slot', 5);
        $response->assertJsonPath('storage.warehouse.vaults.1.number', 2);
        $response->assertJsonPath('storage.warehouse.vaults.1.items.0.slot', 7);
    }

    /**
     * Confirms a character owned by another account is indistinguishable from missing data.
     */
    public function test_it_does_not_return_another_accounts_character_storage(): void
    {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');

        $connection->table('Character')->insert([
            'AccountID' => 'other',
            'Name' => 'HideWizard',
            'Inventory' => $this->blob(76),
        ]);

        $response = $this
            ->actingAs($this->account('owner'))
            ->postItemStorage('HideWizard');

        $response->assertNotFound();
    }

    /**
     * Confirms guests cannot access private item storage.
     */
    public function test_it_requires_authentication(): void
    {
        $response = $this->postItemStorage('OwnKnight');

        $response->assertUnauthorized();
    }

    /**
     * Creates one authenticated game account identity.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: $username,
            displayName: 'Test User',
            email: null,
            blocked: false,
            vip: false,
            password: 'secret1',
        );
    }

    /**
     * Sends an item storage request with a valid web CSRF session.
     */
    private function postItemStorage(string $characterName): TestResponse
    {
        $csrfToken = 'item-storage-endpoint-test-token';

        return $this
            ->withSession(['_token' => $csrfToken])
            ->postJson(route('account.item-storage'), [
                'characterName' => $characterName,
                '_token' => $csrfToken,
            ]);
    }

    /**
     * Converts one readable 10-byte fixture to binary.
     */
    private function item(string $hex): string
    {
        $binary = hex2bin($hex);

        self::assertIsString($binary);

        return $binary;
    }

    /**
     * Creates an exact-sized empty blob with selected occupied slot overrides.
     *
     * @param  array<int, string>  $items
     */
    private function blob(int $slotCount, array $items = []): string
    {
        $slots = array_fill(0, $slotCount, str_repeat("\xFF", 10));

        foreach ($items as $slot => $item) {
            $slots[$slot] = $item;
        }

        return implode('', $slots);
    }
}
