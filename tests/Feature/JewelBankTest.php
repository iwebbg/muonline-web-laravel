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
use App\JewelBank\Actions\StoreJewel;
use App\Market\MarketEscrowResult;
use App\Models\WebStorageItem;
use App\WebStorage\Exceptions\WebStorageRejected;
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
        $this->mock(AccountReadRepository::class)
            ->shouldReceive('charactersFor')
            ->once()
            ->with('owner03')
            ->andReturn([$this->character()]);
        $this->mock(ItemStorageRepository::class)
            ->shouldReceive('forAccountAndCharacter')
            ->once()
            ->andReturn($this->storage($this->item($this->jewelBinary, 7)));

        $response = $this
            ->actingAs($this->account('owner03'))
            ->get(route('account.jewel-bank.index'));

        $response->assertOk();
        $response->assertSee('Jewel of Bless');
        $response->assertViewHas('jewelCounts', fn (array $counts): bool => $counts['14:13'] === 1);
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
