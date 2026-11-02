<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\ItemEscrowRepository;
use App\Game\Contracts\ItemStorageRepository;
use App\Game\Data\AccountCharacter;
use App\Game\Data\AccountItemStorage;
use App\Game\Data\CharacterItemStorage;
use App\Game\Data\ItemSlot;
use App\Game\Data\WarehouseVault;
use App\Game\Versions\Version97d99i\ItemCatalog97d99i;
use App\Game\Versions\Version97d99i\ItemCodec97d99i;
use App\Market\MarketEscrowResult;
use App\Models\WebStorageItem;
use App\WebStorage\Actions\StoreWebStorageItem;
use App\WebStorage\Actions\WithdrawWebStorageItem;
use App\WebStorage\Exceptions\WebStorageRejected;
use App\WebStorage\WebStorageBlockReason;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifies recoverable and owner-scoped exact-item web storage movements.
 */
final class WebStorageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $itemBinary;

    /**
     * Enables mocked game writes and prepares one valid 97d+99i item blob.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);
        $this->itemBinary = "\x00\x00\x20\x01\x02\x03\x04\x00\x00\x00";
    }

    /**
     * Confirms storing removes the selected warehouse item and preserves exact bytes.
     */
    public function test_item_is_extracted_and_becomes_available(): void
    {
        $this->mockOwnedOfflineCharacter('owner01', 'StorageChar');
        $this->mockStorage('owner01', 'StorageChar', 0, 5);
        $this->mock(ItemEscrowRepository::class)
            ->shouldReceive('extract')
            ->once()
            ->with('owner01', 0, 5, $this->itemBinary)
            ->andReturn(MarketEscrowResult::Extracted);

        $item = $this->storeItem('owner01', 'e505c642-0499-4522-bb8d-b8dd83c54289');

        $this->assertSame(WebStorageItemStatus::Available, $item->status);
        $this->assertSame($this->itemBinary, $item->item_binary);
        $this->assertSame(hash('sha256', $this->itemBinary), $item->item_fingerprint);
        $this->assertNotNull($item->stored_at);
    }

    /**
     * Confirms an identical browser replay cannot extract or create a second item.
     */
    public function test_identical_store_replay_returns_original_record(): void
    {
        $this->mockOwnedOfflineCharacter('owner02', 'StorageChar');
        $this->mockStorage('owner02', 'StorageChar', 0, 5);
        $this->mock(ItemEscrowRepository::class)
            ->shouldReceive('extract')
            ->once()
            ->andReturn(MarketEscrowResult::Extracted);
        $key = '0b263f28-9491-41f6-8149-f749e1371098';

        $first = $this->storeItem('owner02', $key);
        $replayed = $this->storeItem('owner02', $key);

        $this->assertSame($first->getKey(), $replayed->getKey());
        $this->assertSame(1, WebStorageItem::query()->count());
    }

    /**
     * Confirms reusing an idempotency key for another item is rejected.
     */
    public function test_idempotency_key_cannot_be_reused_for_another_request(): void
    {
        $this->mockOwnedOfflineCharacter('owner03', 'StorageChar');
        $this->mockStorage('owner03', 'StorageChar', 0, 5);
        $this->mock(ItemEscrowRepository::class)
            ->shouldReceive('extract')
            ->once()
            ->andReturn(MarketEscrowResult::Extracted);
        $key = 'e7e69742-96a9-4828-8677-14ec8fa3cfcc';
        $this->storeItem('owner03', $key);

        try {
            $this->app->make(StoreWebStorageItem::class)->execute(
                accountId: 'owner03',
                characterName: 'StorageChar',
                vault: 0,
                slot: 6,
                idempotencyKey: $key,
            );
            $this->fail('A replay key must remain bound to its original request.');
        } catch (WebStorageRejected $exception) {
            $this->assertSame(WebStorageBlockReason::IdempotencyConflict, $exception->reason);
        }
    }

    /**
     * Confirms interrupted extraction remains pending and is retried by the command.
     */
    public function test_interrupted_extraction_is_retried_by_scheduler(): void
    {
        $this->mockOwnedOfflineCharacter('owner04', 'StorageChar');
        $this->mockStorage('owner04', 'StorageChar', 0, 5);
        $escrow = $this->mock(ItemEscrowRepository::class);
        $escrow->shouldReceive('extract')
            ->once()
            ->andThrow(new RuntimeException('Private infrastructure detail'));
        $item = $this->storeItem('owner04', '2413bbef-2bf3-4665-ab83-8e10fd0b7345');

        $this->assertSame(WebStorageItemStatus::Storing, $item->status);
        $this->assertSame('game_sync_failed', $item->last_sync_error);

        $escrow->shouldReceive('extract')
            ->once()
            ->andReturn(MarketEscrowResult::AlreadyExtracted);
        $this->artisan('web-storage:sync')
            ->expectsOutputToContain('1 synced, 0 pending retry')
            ->assertSuccessful();

        $this->assertSame(WebStorageItemStatus::Available, $item->refresh()->status);
    }

    /**
     * Confirms withdrawal deposits original bytes once and releases the escrow key.
     */
    public function test_owner_withdrawal_deposits_exact_item(): void
    {
        $item = WebStorageItem::factory()->create([
            'account_id' => 'owner05',
            'item_binary' => $this->itemBinary,
            'item_fingerprint' => hash('sha256', $this->itemBinary),
            'escrow_key' => hash('sha256', $this->itemBinary),
        ]);
        $this->mock(ItemEscrowRepository::class)
            ->shouldReceive('deposit')
            ->once()
            ->with('owner05', $this->itemBinary, 1, 2)
            ->andReturn(MarketEscrowResult::Deposited);

        $withdrawn = $this->app->make(WithdrawWebStorageItem::class)->execute(
            accountId: 'owner05',
            itemId: (int) $item->getKey(),
        );

        $this->assertSame(WebStorageItemStatus::Withdrawn, $withdrawn->status);
        $this->assertNull($withdrawn->escrow_key);
        $this->assertNotNull($withdrawn->withdrawn_at);
    }

    /**
     * Confirms another account cannot withdraw an owned web-vault item.
     */
    public function test_withdrawal_is_owner_scoped(): void
    {
        $item = WebStorageItem::factory()->create(['account_id' => 'owner06']);
        $this->mock(ItemEscrowRepository::class)->shouldNotReceive('deposit');

        try {
            $this->app->make(WithdrawWebStorageItem::class)->execute(
                accountId: 'intruder',
                itemId: (int) $item->getKey(),
            );
            $this->fail('Another account must not withdraw an owned item.');
        } catch (WebStorageRejected $exception) {
            $this->assertSame(WebStorageBlockReason::ItemUnavailable, $exception->reason);
        }
    }

    /**
     * Confirms the page renders only authenticated account custody records.
     */
    public function test_page_is_scoped_to_authenticated_account(): void
    {
        WebStorageItem::factory()->create([
            'account_id' => 'pageowner',
            'item_snapshot' => array_merge(
                WebStorageItem::factory()->make()->item_snapshot,
                ['name' => 'Visible custody item'],
            ),
        ]);
        WebStorageItem::factory()->create([
            'account_id' => 'otheracct',
            'item_snapshot' => array_merge(
                WebStorageItem::factory()->make()->item_snapshot,
                ['name' => 'Hidden custody item'],
            ),
        ]);
        $this->mock(AccountReadRepository::class)
            ->shouldReceive('charactersFor')
            ->once()
            ->with('pageowner')
            ->andReturn([$this->character('StorageChar')]);
        $this->mock(ItemStorageRepository::class)
            ->shouldReceive('forAccountAndCharacter')
            ->once()
            ->with('pageowner', 'StorageChar')
            ->andReturn(new AccountItemStorage(
                character: new CharacterItemStorage('StorageChar', [], []),
                warehouseZen: 0,
                vaults: [],
            ));

        $response = $this
            ->actingAs($this->account('pageowner'))
            ->get(route('account.web-storage.index'));

        $response
            ->assertOk()
            ->assertSee('Visible custody item')
            ->assertDontSee('Hidden custody item');
    }

    /**
     * Executes the production storage action with common item coordinates.
     */
    private function storeItem(string $accountId, string $idempotencyKey): WebStorageItem
    {
        return $this->app->make(StoreWebStorageItem::class)->execute(
            accountId: $accountId,
            characterName: 'StorageChar',
            vault: 0,
            slot: 5,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * Configures ownership lookup for one offline character.
     */
    private function mockOwnedOfflineCharacter(string $accountId, string $characterName): void
    {
        $this->mock(AccountReadRepository::class)
            ->shouldReceive('characterFor')
            ->once()
            ->with($accountId, $characterName)
            ->andReturn($this->character($characterName));
    }

    /**
     * Configures one warehouse vault containing the selected exact item.
     */
    private function mockStorage(
        string $accountId,
        string $characterName,
        int $vault,
        int $slot,
    ): void {
        $storage = new AccountItemStorage(
            character: new CharacterItemStorage($characterName, [], []),
            warehouseZen: 0,
            vaults: [new WarehouseVault($vault, true, [$this->item($slot)])],
        );
        $this->mock(ItemStorageRepository::class)
            ->shouldReceive('forAccountAndCharacter')
            ->once()
            ->with($accountId, $characterName)
            ->andReturn($storage);
    }

    /**
     * Builds one catalog-enriched item while preserving original bytes.
     */
    private function item(int $slot): ItemSlot
    {
        $codec = $this->app->make(ItemCodec97d99i::class);
        $catalog = $this->app->make(ItemCatalog97d99i::class);
        $item = $codec->decode($this->itemBinary, 1)[0]->withSlot($slot);
        $definition = $catalog->definitionFor((int) $item->group, (int) $item->index);

        return $definition === null ? $item : $item->withDefinition($definition);
    }

    /**
     * Builds one offline character DTO for ownership checks.
     */
    private function character(string $name): AccountCharacter
    {
        return new AccountCharacter(
            name: $name,
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
