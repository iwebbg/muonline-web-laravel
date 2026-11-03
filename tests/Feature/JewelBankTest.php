<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\CharacterInventoryEscrowRepository;
use App\Game\Contracts\ItemEscrowRepository;
use App\Game\Contracts\ItemStorageRepository;
use App\Game\Data\AccountCharacter;
use App\Game\Data\AccountItemStorage;
use App\Game\Data\CharacterItemStorage;
use App\Game\Data\ItemSlot;
use App\Game\Data\WarehouseVault;
use App\Game\Versions\Version97d99i\ItemCatalog97d99i;
use App\Game\Versions\Version97d99i\ItemCodec97d99i;
use App\JewelBank\Actions\StoreJewel;
use App\JewelBank\Actions\StoreJewelBatch;
use App\JewelBank\Actions\WithdrawJewelBatch;
use App\Market\MarketEscrowResult;
use App\Models\WebStorageItem;
use App\WebStorage\Actions\WithdrawWebStorageItem;
use App\WebStorage\Exceptions\WebStorageRejected;
use App\WebStorage\ItemStorageLocation;
use App\WebStorage\WebStorageBlockReason;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that Jewel Bank balances remain backed by original exact items.
 */
final class JewelBankTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $jewelBinary;

    /**
     * Enables mocked game writes and prepares one Jewel of Bless item blob.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);
        $this->jewelBinary = "\xCD\x00\x20\x01\x02\x03\x04\x80\x00\x00";
    }

    /**
     * Confirms one supported jewel enters custody with its original bytes.
     */
    public function test_supported_jewel_is_stored_as_an_exact_item(): void
    {
        $storage = $this->storage($this->item($this->jewelBinary, 5));
        $this->mock(ItemStorageRepository::class)
            ->shouldReceive('forAccountAndCharacter')
            ->twice()
            ->with('owner01', 'JewelChar')
            ->andReturn($storage);
        $this->mock(AccountReadRepository::class)
            ->shouldReceive('characterFor')
            ->once()
            ->with('owner01', 'JewelChar')
            ->andReturn($this->character());
        $this->mock(ItemEscrowRepository::class)
            ->shouldReceive('extract')
            ->once()
            ->with('owner01', 0, 5, $this->jewelBinary)
            ->andReturn(MarketEscrowResult::Extracted);

        $stored = $this->app->make(StoreJewel::class)->execute(
            accountId: 'owner01',
            characterName: 'JewelChar',
            vault: 0,
            slot: 5,
            idempotencyKey: '54202371-d769-4f1a-90d0-5ef12bac40d2',
        );

        $this->assertSame(WebStorageItemStatus::Available, $stored->status);
        $this->assertSame('14:13', $stored->item_snapshot['code']);
        $this->assertSame($this->jewelBinary, $stored->item_binary);
    }

    /**
     * Confirms a deposit replay resolves the original item after warehouse extraction.
     */
    public function test_supported_jewel_deposit_is_idempotent_after_extraction(): void
    {
        $storage = $this->storage($this->item($this->jewelBinary, 5));
        $this->mock(ItemStorageRepository::class)
            ->shouldReceive('forAccountAndCharacter')
            ->twice()
            ->andReturn($storage);
        $this->mock(AccountReadRepository::class)
            ->shouldReceive('characterFor')
            ->once()
            ->andReturn($this->character());
        $this->mock(ItemEscrowRepository::class)
            ->shouldReceive('extract')
            ->once()
            ->andReturn(MarketEscrowResult::Extracted);
        $key = 'dd549cf8-fca0-414d-b2e8-a8aaf450fb5a';

        $stored = $this->app->make(StoreJewel::class)->execute(
            accountId: 'owner04',
            characterName: 'JewelChar',
            vault: 0,
            slot: 5,
            idempotencyKey: $key,
        );
        $replayed = $this->app->make(StoreJewel::class)->execute(
            accountId: 'owner04',
            characterName: 'JewelChar',
            vault: 0,
            slot: 5,
            idempotencyKey: $key,
        );

        $this->assertSame($stored->getKey(), $replayed->getKey());
        $this->assertSame(1, WebStorageItem::query()->count());
    }

    /**
     * Confirms arbitrary warehouse items cannot be represented as Jewel Bank value.
     */
    public function test_non_jewel_item_is_rejected_before_extraction(): void
    {
        $ordinaryItem = "\x00\x00\x20\x01\x02\x03\x04\x00\x00\x00";
        $this->mock(ItemStorageRepository::class)
            ->shouldReceive('forAccountAndCharacter')
            ->once()
            ->andReturn($this->storage($this->item($ordinaryItem, 5)));
        $this->mock(ItemEscrowRepository::class)->shouldNotReceive('extract');

        try {
            $this->app->make(StoreJewel::class)->execute(
                accountId: 'owner02',
                characterName: 'JewelChar',
                vault: 0,
                slot: 5,
                idempotencyKey: '94121ec4-f0ae-4580-8e6e-b40ae083916d',
            );
            $this->fail('A non-jewel item must never enter the Jewel Bank.');
        } catch (WebStorageRejected $exception) {
            $this->assertSame(WebStorageBlockReason::ItemUnavailable, $exception->reason);
        }
    }

    /**
     * Confirms bag-only jewels return to the same character inventory safely.
     */
    public function test_character_bag_jewel_returns_to_character_bag(): void
    {
        $item = $this->item($this->jewelBinary, 9);
        $storage = new AccountItemStorage(
            character: new CharacterItemStorage('JewelChar', [], [$item]),
            warehouseZen: 0,
            vaults: [],
        );
        $this->mock(ItemStorageRepository::class)
            ->shouldReceive('forAccountAndCharacter')
            ->twice()
            ->with('owner05', 'JewelChar')
            ->andReturn($storage);
        $this->mock(AccountReadRepository::class)
            ->shouldReceive('characterFor')
            ->twice()
            ->with('owner05', 'JewelChar')
            ->andReturn($this->character());
        $inventoryEscrowRepository = $this->mock(CharacterInventoryEscrowRepository::class);
        $inventoryEscrowRepository
            ->shouldReceive('extract')
            ->once()
            ->with('owner05', 'JewelChar', 9, $this->jewelBinary)
            ->andReturn(MarketEscrowResult::Extracted);
        $inventoryEscrowRepository
            ->shouldReceive('deposit')
            ->once()
            ->with('owner05', 'JewelChar', $this->jewelBinary, 1, 1)
            ->andReturn(MarketEscrowResult::Deposited);

        $stored = $this->app->make(StoreJewel::class)->execute(
            accountId: 'owner05',
            characterName: 'JewelChar',
            vault: 0,
            slot: 9,
            idempotencyKey: '29cf7e37-d44c-41f0-a55f-3978502ab41e',
            sourceLocation: ItemStorageLocation::Inventory,
        );
        $withdrawn = $this->app->make(WithdrawWebStorageItem::class)->execute(
            accountId: 'owner05',
            itemId: (int) $stored->getKey(),
            destinationLocation: ItemStorageLocation::Inventory,
            destinationCharacterName: 'JewelChar',
        );

        $this->assertSame(ItemStorageLocation::Inventory, $stored->source_location);
        $this->assertSame(WebStorageItemStatus::Withdrawn, $withdrawn->status);
        $this->assertSame(ItemStorageLocation::Inventory, $withdrawn->withdrawal_location);
        $this->assertSame('JewelChar', $withdrawn->withdrawal_character_name);
    }

    /**
     * Confirms a grouped deposit stores only the requested physical jewels.
     */
    public function test_grouped_deposit_stores_requested_physical_quantity(): void
    {
        $firstBinary = $this->jewelBinary;
        $secondBinary = substr_replace($this->jewelBinary, "\x04", 4, 1);
        $thirdBinary = substr_replace($this->jewelBinary, "\x05", 4, 1);
        $storage = new AccountItemStorage(
            character: new CharacterItemStorage('JewelChar', [], [
                $this->item($firstBinary, 2),
                $this->item($secondBinary, 5),
                $this->item($thirdBinary, 8),
            ]),
            warehouseZen: 0,
            vaults: [],
        );
        $this->mock(ItemStorageRepository::class)
            ->shouldReceive('forAccountAndCharacter')
            ->times(5)
            ->with('owner07', 'JewelChar')
            ->andReturn($storage);
        $this->mock(AccountReadRepository::class)
            ->shouldReceive('characterFor')
            ->twice()
            ->with('owner07', 'JewelChar')
            ->andReturn($this->character());
        $inventoryEscrowRepository = $this->mock(CharacterInventoryEscrowRepository::class);
        $inventoryEscrowRepository
            ->shouldReceive('extract')
            ->once()
            ->with('owner07', 'JewelChar', 2, $firstBinary)
            ->andReturn(MarketEscrowResult::Extracted);
        $inventoryEscrowRepository
            ->shouldReceive('extract')
            ->once()
            ->with('owner07', 'JewelChar', 5, $secondBinary)
            ->andReturn(MarketEscrowResult::Extracted);

        $storedItems = $this->app->make(StoreJewelBatch::class)->execute(
            accountId: 'owner07',
            characterName: 'JewelChar',
            vault: 0,
            sourceLocation: ItemStorageLocation::Inventory,
            jewelCode: '14:13',
            jewelLevel: 0,
            quantity: 2,
            batchIdempotencyKey: '8d7679a2-36e7-4ceb-9d28-f17829cb7279',
        );

        $this->assertCount(2, $storedItems);
        $this->assertSame(2, WebStorageItem::query()->count());
        $this->assertSame(WebStorageItemStatus::Available, $storedItems[0]->status);
        $this->assertSame(WebStorageItemStatus::Available, $storedItems[1]->status);
    }

    /**
     * Confirms a manipulated quantity cannot exceed the current physical count.
     */
    public function test_grouped_deposit_rejects_quantity_above_current_physical_count(): void
    {
        $csrfToken = 'jewel-bank-quantity-token';
        $storage = new AccountItemStorage(
            character: new CharacterItemStorage('JewelChar', [], [
                $this->item($this->jewelBinary, 2),
                $this->item(substr_replace($this->jewelBinary, "\x04", 4, 1), 5),
            ]),
            warehouseZen: 0,
            vaults: [],
        );
        $this->mock(ItemStorageRepository::class)
            ->shouldReceive('forAccountAndCharacter')
            ->once()
            ->with('owner08', 'JewelChar')
            ->andReturn($storage);
        $this->mock(CharacterInventoryEscrowRepository::class)
            ->shouldNotReceive('extract');

        $response = $this
            ->from(route('account.jewel-bank.index'))
            ->withSession(['_token' => $csrfToken])
            ->actingAs($this->account('owner08'))
            ->post(route('account.jewel-bank.store'), [
                '_token' => $csrfToken,
                'character_name' => 'JewelChar',
                'source_location' => ItemStorageLocation::Inventory->value,
                'vault' => 0,
                'jewel_code' => '14:13',
                'jewel_level' => 0,
                'quantity' => 3,
                'idempotency_key' => 'd4fb5232-d476-4c84-8a2d-f235d7b37d7f',
            ]);

        $response->assertRedirect(route('account.jewel-bank.index'));
        $response->assertSessionHasErrors('jewelBank');
        $this->assertSame(0, WebStorageItem::query()->count());
    }

    /**
     * Confirms a grouped withdrawal returns only the requested available quantity.
     */
    public function test_grouped_withdrawal_returns_requested_available_quantity(): void
    {
        $firstBinary = $this->jewelBinary;
        $secondBinary = substr_replace($this->jewelBinary, "\x04", 4, 1);
        $thirdBinary = substr_replace($this->jewelBinary, "\x05", 4, 1);
        $firstItem = WebStorageItem::factory()->create([
            'account_id' => 'owner09',
            'item_binary' => $firstBinary,
            'item_fingerprint' => hash('sha256', $firstBinary),
            'escrow_key' => hash('sha256', $firstBinary),
            'item_snapshot' => $this->item($firstBinary, 2)->toArray(),
        ]);
        $secondItem = WebStorageItem::factory()->create([
            'account_id' => 'owner09',
            'source_location' => ItemStorageLocation::Inventory,
            'source_character_name' => 'JewelChar',
            'item_binary' => $secondBinary,
            'item_fingerprint' => hash('sha256', $secondBinary),
            'escrow_key' => hash('sha256', $secondBinary),
            'item_snapshot' => $this->item($secondBinary, 5)->toArray(),
        ]);
        $thirdItem = WebStorageItem::factory()->create([
            'account_id' => 'owner09',
            'item_binary' => $thirdBinary,
            'item_fingerprint' => hash('sha256', $thirdBinary),
            'escrow_key' => hash('sha256', $thirdBinary),
            'item_snapshot' => $this->item($thirdBinary, 8)->toArray(),
        ]);
        $this->mock(ItemEscrowRepository::class)
            ->shouldReceive('deposit')
            ->twice()
            ->andReturn(MarketEscrowResult::Deposited);

        $withdrawnItems = $this->app->make(WithdrawJewelBatch::class)->execute(
            accountId: 'owner09',
            jewelCode: '14:13',
            jewelLevel: 0,
            quantity: 2,
            destinationLocation: ItemStorageLocation::Warehouse,
            destinationCharacterName: null,
        );

        $this->assertCount(2, $withdrawnItems);
        $this->assertSame(WebStorageItemStatus::Withdrawn, $firstItem->refresh()->status);
        $this->assertSame(WebStorageItemStatus::Withdrawn, $secondItem->refresh()->status);
        $this->assertSame(WebStorageItemStatus::Available, $thirdItem->refresh()->status);
        $this->assertSame(ItemStorageLocation::Warehouse, $secondItem->withdrawal_location);
        $this->assertNull($secondItem->withdrawal_character_name);
    }

    /**
     * Confirms a forged character destination cannot claim another inventory.
     */
    public function test_grouped_withdrawal_rejects_an_unowned_character_destination(): void
    {
        $item = WebStorageItem::factory()->create([
            'account_id' => 'owner11',
            'item_binary' => $this->jewelBinary,
            'item_fingerprint' => hash('sha256', $this->jewelBinary),
            'escrow_key' => hash('sha256', $this->jewelBinary),
            'item_snapshot' => $this->item($this->jewelBinary, 2)->toArray(),
        ]);
        $this->mock(AccountReadRepository::class)
            ->shouldReceive('characterFor')
            ->once()
            ->with('owner11', 'ForeignHero')
            ->andReturnNull();
        $this->mock(CharacterInventoryEscrowRepository::class)
            ->shouldNotReceive('deposit');

        try {
            $this->app->make(WithdrawJewelBatch::class)->execute(
                accountId: 'owner11',
                jewelCode: '14:13',
                jewelLevel: 0,
                quantity: 1,
                destinationLocation: ItemStorageLocation::Inventory,
                destinationCharacterName: 'ForeignHero',
            );
            $this->fail('An unowned character must never receive a bank jewel.');
        } catch (WebStorageRejected $exception) {
            $this->assertSame(WebStorageBlockReason::CharacterNotFound, $exception->reason);
        }

        $this->assertSame(WebStorageItemStatus::Available, $item->refresh()->status);
        $this->assertNull($item->withdrawal_location);
    }

    /**
     * Confirms a manipulated grouped withdrawal cannot exceed live custody.
     */
    public function test_grouped_withdrawal_rejects_quantity_above_available_balance(): void
    {
        $csrfToken = 'jewel-bank-withdrawal-token';
        WebStorageItem::factory()->count(2)->create([
            'account_id' => 'owner10',
            'item_snapshot' => $this->item($this->jewelBinary, 2)->toArray(),
        ]);
        $this->mock(ItemEscrowRepository::class)
            ->shouldNotReceive('deposit');

        $response = $this
            ->from(route('account.jewel-bank.index'))
            ->withSession(['_token' => $csrfToken])
            ->actingAs($this->account('owner10'))
            ->post(route('account.jewel-bank.withdrawals.store'), [
                '_token' => $csrfToken,
                'jewel_code' => '14:13',
                'jewel_level' => 0,
                'quantity' => 3,
                'destination_location' => ItemStorageLocation::Warehouse->value,
                'character_name' => '',
            ]);

        $response->assertRedirect(route('account.jewel-bank.index'));
        $response->assertSessionHasErrors('jewelBank');
        $this->assertSame(
            2,
            WebStorageItem::query()
                ->where('account_id', 'owner10')
                ->where('status', WebStorageItemStatus::Available->value)
                ->count(),
        );
    }

    /**
     * Confirms the account page counts only available, physical jewel records.
     */
    public function test_page_displays_physical_available_jewel_count(): void
    {
        WebStorageItem::factory()->create([
            'account_id' => 'owner03',
            'item_binary' => $this->jewelBinary,
            'item_fingerprint' => hash('sha256', $this->jewelBinary),
            'escrow_key' => hash('sha256', $this->jewelBinary),
            'item_snapshot' => $this->item($this->jewelBinary, 5)->toArray(),
        ]);
        WebStorageItem::factory()->create([
            'account_id' => 'owner03',
            'status' => WebStorageItemStatus::Withdrawn,
            'escrow_key' => null,
            'item_snapshot' => $this->item($this->jewelBinary, 6)->toArray(),
        ]);
        $inventoryBinary = substr_replace($this->jewelBinary, "\x06", 4, 1);
        WebStorageItem::factory()->create([
            'account_id' => 'owner03',
            'source_location' => ItemStorageLocation::Inventory,
            'source_character_name' => 'JewelChar',
            'item_binary' => $inventoryBinary,
            'item_fingerprint' => hash('sha256', $inventoryBinary),
            'escrow_key' => hash('sha256', $inventoryBinary),
            'item_snapshot' => $this->item($inventoryBinary, 9)->toArray(),
        ]);
        $this->mock(AccountReadRepository::class)
            ->shouldReceive('charactersFor')
            ->once()
            ->with('owner03')
            ->andReturn([$this->character()]);
        $this->mock(ItemStorageRepository::class)
            ->shouldReceive('forAccount')
            ->once()
            ->with('owner03')
            ->andReturn(new AccountItemStorage(
                character: new CharacterItemStorage('', [], []),
                warehouseZen: 0,
                vaults: [
                    new WarehouseVault(1, true, []),
                    new WarehouseVault(2, false, [$this->item($this->jewelBinary, 7)]),
                ],
            ));

        $response = $this
            ->actingAs($this->account('owner03'))
            ->get(route('account.jewel-bank.index', ['vault' => 2]));

        $response->assertOk();
        $response->assertSee('Jewel of Bless');
        $response->assertSee('Main Vault');
        $response->assertSee('Character Inventory');
        $response->assertSee('Vault 2');
        $response->assertDontSee('Vault 3');
        $response->assertViewHas('jewelCounts', fn (array $counts): bool => $counts['14:13'] === 2);
        $response->assertViewHas('storedJewelGroups', fn (array $groups): bool => count($groups) === 1
            && $groups[0]['quantity'] === 2);
        $response->assertViewHas('jewelHistory', fn ($history): bool => $history->total() === 3);
    }

    /**
     * Confirms a character bag is loaded independently from account vaults.
     */
    public function test_page_can_load_supported_jewels_from_character_bag(): void
    {
        $this->mock(AccountReadRepository::class)
            ->shouldReceive('charactersFor')
            ->once()
            ->with('owner06')
            ->andReturn([$this->character()]);
        $this->mock(ItemStorageRepository::class)
            ->shouldReceive('forAccountAndCharacter')
            ->once()
            ->with('owner06', 'JewelChar')
            ->andReturn(new AccountItemStorage(
                character: new CharacterItemStorage('JewelChar', [], [
                    $this->item($this->jewelBinary, 3),
                    $this->item(substr_replace($this->jewelBinary, "\x04", 4, 1), 6),
                    $this->item(substr_replace($this->jewelBinary, "\x05", 4, 1), 9),
                ]),
                warehouseZen: 0,
                vaults: [],
            ));

        $response = $this
            ->actingAs($this->account('owner06'))
            ->get(route('account.jewel-bank.index', [
                'source' => ItemStorageLocation::Inventory->value,
                'character' => 'JewelChar',
            ]));

        $response->assertOk();
        $response->assertSee('JewelChar inventory bag');
        $response->assertSee('Jewel of Bless');
        $response->assertSee('3 available');
        $response->assertSee('Choose deposit quantity');
    }

    /**
     * Creates one warehouse snapshot containing a single decoded item.
     */
    private function storage(ItemSlot $item): AccountItemStorage
    {
        return new AccountItemStorage(
            character: new CharacterItemStorage('JewelChar', [], []),
            warehouseZen: 0,
            vaults: [new WarehouseVault(0, true, [$item])],
        );
    }

    /**
     * Decodes and enriches one exact item blob at the requested warehouse slot.
     */
    private function item(string $binary, int $slot): ItemSlot
    {
        $codec = $this->app->make(ItemCodec97d99i::class);
        $catalog = $this->app->make(ItemCatalog97d99i::class);
        $item = $codec->decode($binary, 1)[0]->withSlot($slot);
        $definition = $catalog->definitionFor((int) $item->group, (int) $item->index);

        return $definition === null ? $item : $item->withDefinition($definition);
    }

    /**
     * Builds one offline character DTO for ownership checks.
     */
    private function character(): AccountCharacter
    {
        return new AccountCharacter(
            name: 'JewelChar',
            className: 'Blade Knight',
            classCode: 17,
            image: '/images/characters/blade-knight.webp',
            level: 400,
            resets: 20,
            grandResets: 2,
            zen: 0,
            points: 0,
            strength: 100,
            agility: 100,
            vitality: 100,
            energy: 100,
            map: 'Lorencia',
            online: false,
        );
    }

    /**
     * Creates one authenticated legacy account identity.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: $username,
            displayName: $username,
            email: null,
            blocked: false,
            vip: false,
            password: 'secret',
        );
    }
}
