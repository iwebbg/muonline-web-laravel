<?php

namespace Tests\Feature;

use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\ItemStorageRepository;
use App\Game\Contracts\MarketItemEscrowRepository;
use App\Game\Data\AccountCharacter;
use App\Game\Data\AccountItemStorage;
use App\Game\Data\CharacterItemStorage;
use App\Game\Data\ItemSlot;
use App\Game\Data\WarehouseVault;
use App\Game\Repositories\SqlServer97d99iMarketItemEscrowRepository;
use App\Game\Versions\Version97d99i\ItemCatalog97d99i;
use App\Game\Versions\Version97d99i\ItemCodec97d99i;
use App\Market\Actions\CreateMarketListing;
use App\Market\Actions\ReturnMarketListing;
use App\Market\AuctionDuration;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketEscrowResult;
use App\Market\MarketListingBlockReason;
use App\Market\MarketListingStatus;
use App\Market\MarketSaleType;
use App\Market\MarketStorageDestination;
use App\Models\MarketListing;
use App\Models\WebStorageItem;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Grammars\SqlServerGrammar;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifies exact-byte market extraction, recovery and duplicate-safe returns.
 */
final class MarketEscrowTest extends TestCase
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
     * Confirms listing creation stores original bytes before publishing escrow.
     */
    public function test_listing_extracts_exact_item_and_becomes_active(): void
    {
        $this->mockOwnedOfflineCharacter('seller01', 'SellerChar');
        $this->mockStorage('seller01', 'SellerChar', vault: 0, slot: 5);
        $escrow = $this->mock(MarketItemEscrowRepository::class);
        $escrow->shouldReceive('extract')
            ->once()
            ->with('seller01', 0, 5, $this->itemBinary)
            ->andReturn(MarketEscrowResult::Extracted);

        $listing = $this->createListing(
            accountId: 'seller01',
            characterName: 'SellerChar',
            vault: 0,
            slot: 5,
            priceCredits: 500,
            idempotencyKey: '67931593-5859-42eb-b834-23875cc396ce',
        );

        $this->assertSame(MarketListingStatus::Active, $listing->status);
        $this->assertSame($this->itemBinary, $listing->item_binary);
        $this->assertSame(hash('sha256', $this->itemBinary), $listing->item_fingerprint);
        $this->assertSame(500, $listing->price_credits);
    }

    /**
     * Confirms the selected auction duration controls the public expiry time.
     */
    public function test_auction_uses_the_selected_duration(): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->mockOwnedOfflineCharacter('seller11', 'AuctionChr');
        $this->mockStorage('seller11', 'AuctionChr', vault: 0, slot: 5);
        $this->mock(MarketItemEscrowRepository::class)
            ->shouldReceive('extract')
            ->once()
            ->with('seller11', 0, 5, $this->itemBinary)
            ->andReturn(MarketEscrowResult::Extracted);

        $listing = $this->app->make(CreateMarketListing::class)->execute(
            accountId: 'seller11',
            characterName: 'AuctionChr',
            vault: 0,
            slot: 5,
            priceCredits: 500,
            idempotencyKey: '4dbd7f28-1549-440a-996a-7dc0dfc2b3ea',
            saleType: MarketSaleType::Auction,
            bidIncrementCredits: 25,
            auctionDuration: AuctionDuration::SixHours,
        );

        $this->assertSame(MarketSaleType::Auction, $listing->sale_type);
        $this->assertTrue($listing->expires_at->equalTo(now()->addHours(6)));
    }

    /**
     * Confirms an identical request replay cannot extract or create twice.
     */
    public function test_listing_idempotency_replay_returns_original_listing(): void
    {
        $this->mockOwnedOfflineCharacter('seller02', 'ReplayChar');
        $this->mockStorage('seller02', 'ReplayChar', vault: 0, slot: 6);
        $this->mock(MarketItemEscrowRepository::class)
            ->shouldReceive('extract')
            ->once()
            ->andReturn(MarketEscrowResult::Extracted);
        $arguments = [
            'accountId' => 'seller02',
            'characterName' => 'ReplayChar',
            'vault' => 0,
            'slot' => 6,
            'priceCredits' => 250,
            'idempotencyKey' => '58516402-4a2e-422f-bdb4-b0498d570600',
        ];

        $first = $this->createListing(...$arguments);
        $replayed = $this->createListing(...$arguments);

        $this->assertSame($first->getKey(), $replayed->getKey());
        $this->assertSame(1, MarketListing::query()->count());
    }

    /**
     * Confirms the same serial bytes cannot enter two concurrent escrow listings.
     */
    public function test_same_item_fingerprint_cannot_be_listed_twice(): void
    {
        $reader = $this->mock(AccountReadRepository::class);
        $reader->shouldReceive('characterFor')
            ->twice()
            ->with('seller06', 'SerialChar')
            ->andReturn($this->character('SerialChar'));
        $storageData = new AccountItemStorage(
            character: new CharacterItemStorage('SerialChar', [], []),
            warehouseZen: 0,
            vaults: [new WarehouseVault(0, true, [$this->item(8)])],
        );
        $storage = $this->mock(ItemStorageRepository::class);
        $storage->shouldReceive('forAccountAndCharacter')
            ->twice()
            ->andReturn($storageData);
        $this->mock(MarketItemEscrowRepository::class)
            ->shouldReceive('extract')
            ->once()
            ->andReturn(MarketEscrowResult::Extracted);

        $this->createListing('seller06', 'SerialChar', 0, 8, 100, '67eb6636-f0be-4dba-a58c-962e0b24831d');

        try {
            $this->createListing('seller06', 'SerialChar', 0, 8, 200, 'a0e839c8-e1d2-4e27-9990-d95f9e7690b8');
            $this->fail('One exact item serial must not enter escrow twice.');
        } catch (MarketListingRejected $exception) {
            $this->assertSame(MarketListingBlockReason::ItemChanged, $exception->reason);
        }

        $this->assertSame(1, MarketListing::query()->count());
    }

    /**
     * Confirms an interrupted extraction remains recoverable by the scheduler.
     */
    public function test_failed_extraction_is_retried_by_scheduler(): void
    {
        $this->mockOwnedOfflineCharacter('seller03', 'RetryChar');
        $this->mockStorage('seller03', 'RetryChar', vault: 0, slot: 7);
        $escrow = $this->mock(MarketItemEscrowRepository::class);
        $escrow->shouldReceive('extract')
            ->once()
            ->andThrow(new RuntimeException('Infrastructure detail'));

        $listing = $this->createListing(
            accountId: 'seller03',
            characterName: 'RetryChar',
            vault: 0,
            slot: 7,
            priceCredits: 300,
            idempotencyKey: '272a0949-f991-454d-a034-dd31b9f87026',
        );

        $this->assertSame(MarketListingStatus::Extracting, $listing->status);
        $this->assertSame('game_sync_failed', $listing->last_sync_error);

        $escrow->shouldReceive('extract')
            ->once()
            ->andReturn(MarketEscrowResult::AlreadyExtracted);
        $this->artisan('market:sync-escrow')
            ->expectsOutputToContain('1 synced, 0 pending retry')
            ->assertSuccessful();

        $this->assertSame(MarketListingStatus::Active, $listing->refresh()->status);
    }

    /**
     * Confirms expired listings leave public sale and return their exact item.
     */
    public function test_scheduler_returns_expired_active_listing_to_seller(): void
    {
        $listing = MarketListing::factory()->create([
            'seller_account_id' => 'seller09',
            'expires_at' => now()->subMinute(),
        ]);
        $this->mock(MarketItemEscrowRepository::class)
            ->shouldReceive('deposit')
            ->once()
            ->with('seller09', $listing->item_binary, 1, 2)
            ->andReturn(MarketEscrowResult::Deposited);

        $this->artisan('market:sync-escrow')
            ->expectsOutputToContain('1 synced, 0 pending retry')
            ->assertSuccessful();

        $listing->refresh();
        $this->assertSame(MarketListingStatus::Returned, $listing->status);
        $this->assertNull($listing->escrow_key);
        $this->assertNotNull($listing->returned_at);
    }

    /**
     * Confirms seller cancellation removes the listing and returns the same bytes.
     */
    public function test_seller_return_deposits_original_item_once(): void
    {
        $listing = MarketListing::factory()->create([
            'seller_account_id' => 'seller04',
            'item_binary' => $this->itemBinary,
            'item_fingerprint' => hash('sha256', $this->itemBinary),
            'escrow_key' => hash('sha256', $this->itemBinary),
        ]);
        $escrow = $this->mock(MarketItemEscrowRepository::class);
        $escrow->shouldReceive('deposit')
            ->once()
            ->with('seller04', $this->itemBinary, 1, 2)
            ->andReturn(MarketEscrowResult::Deposited);

        $returned = $this->app->make(ReturnMarketListing::class)->execute(
            accountId: 'seller04',
            listingId: (int) $listing->getKey(),
        );

        $this->assertSame(MarketListingStatus::Returned, $returned->status);
        $this->assertNotNull($returned->returned_at);
    }

    /**
     * Confirms seller cancellation falls back to web storage when the game vault is full.
     */
    public function test_seller_return_falls_back_to_web_storage_when_game_vault_is_full(): void
    {
        $listing = MarketListing::factory()->create([
            'seller_account_id' => 'seller10',
            'item_binary' => $this->itemBinary,
            'item_fingerprint' => hash('sha256', $this->itemBinary),
            'escrow_key' => hash('sha256', $this->itemBinary),
        ]);
        $this->mock(MarketItemEscrowRepository::class)
            ->shouldReceive('deposit')
            ->once()
            ->andThrow(new MarketListingRejected(MarketListingBlockReason::StorageFull));

        $returned = $this->app->make(ReturnMarketListing::class)->execute(
            accountId: 'seller10',
            listingId: (int) $listing->getKey(),
        );
        $storedItem = WebStorageItem::query()->sole();

        $this->assertSame(MarketListingStatus::Returned, $returned->status);
        $this->assertSame(MarketStorageDestination::WebVault, $returned->storage_destination);
        $this->assertNull($returned->escrow_key);
        $this->assertSame('seller10', $storedItem->account_id);
        $this->assertSame(WebStorageItemStatus::Available, $storedItem->status);
        $this->assertSame($this->itemBinary, $storedItem->item_binary);
    }

    /**
     * Confirms the 97d+99i adapter extracts and re-deposits lossless item bytes.
     */
    public function test_adapter_round_trip_preserves_exact_item_bytes(): void
    {
        $database = $this->configurePortableGameSchema(slot: 5);
        $repository = $this->repository($database);

        $extracted = $repository->extract('seller05', 0, 5, $this->itemBinary);
        $afterExtraction = $database->connection('game')
            ->table('warehouse')
            ->value('Items');
        $deposited = $repository->deposit('seller05', $this->itemBinary, 1, 2);
        $afterDeposit = $database->connection('game')
            ->table('warehouse')
            ->value('Items');

        $this->assertSame(MarketEscrowResult::Extracted, $extracted);
        $this->assertSame(str_repeat("\xFF", 10), substr($afterExtraction, 50, 10));
        $this->assertSame(MarketEscrowResult::Deposited, $deposited);
        $this->assertStringContainsString($this->itemBinary, $afterDeposit);
    }

    /**
     * Confirms deposit retries detect the exact serial bytes and never duplicate.
     */
    public function test_adapter_deposit_is_idempotent_for_existing_item_bytes(): void
    {
        $database = $this->configurePortableGameSchema(slot: 5);
        $repository = $this->repository($database);

        $result = $repository->deposit('seller05', $this->itemBinary, 1, 2);

        $this->assertSame(MarketEscrowResult::AlreadyDeposited, $result);
    }

    /**
     * Confirms online and unknown statuses leave warehouse item bytes unchanged.
     */
    #[DataProvider('unsafeConnectionStatuses')]
    public function test_adapter_rejects_warehouse_mutation_without_explicit_offline_status(
        bool $createStatusRow,
        ?int $connectStatus,
    ): void {
        $database = $this->configurePortableGameSchema(
            slot: 5,
            createStatusRow: $createStatusRow,
            connectStatus: $connectStatus,
        );
        $repository = $this->repository($database);
        $beforeWarehouse = $database->connection('game')->table('warehouse')->value('Items');

        try {
            $repository->extract('seller05', 0, 5, $this->itemBinary);
            $this->fail('An account without explicit offline status must not mutate its warehouse.');
        } catch (MarketListingRejected $exception) {
            $this->assertSame(MarketListingBlockReason::CharacterOnline, $exception->reason);
        }

        $afterWarehouse = $database->connection('game')->table('warehouse')->value('Items');

        $this->assertSame($beforeWarehouse, $afterWarehouse);
    }

    /**
     * Provides every connection state that cannot prove an account is offline.
     *
     * @return array<string, array{bool, int|null}>
     */
    public static function unsafeConnectionStatuses(): array
    {
        return [
            'online' => [true, 1],
            'null status' => [true, null],
            'missing status row' => [false, null],
        ];
    }

    /**
     * Confirms game delivery rejects a vault without a free item rectangle.
     */
    public function test_adapter_rejects_deposit_when_game_vault_has_no_item_space(): void
    {
        $database = $this->configurePortableGameSchema(slot: 5);
        $occupiedItem = "\x01\x00\x20\x09\x08\x07\x06\x00\x00\x00";
        $database->connection('game')
            ->table('warehouse')
            ->where('AccountID', 'seller05')
            ->update(['Items' => str_repeat($occupiedItem, 120)]);
        $repository = $this->repository($database);

        try {
            $repository->deposit('seller05', $this->itemBinary, 1, 2);
            $this->fail('A full game vault must reject item delivery.');
        } catch (MarketListingRejected $exception) {
            $this->assertSame(MarketListingBlockReason::StorageFull, $exception->reason);
        }
    }

    /**
     * Confirms SQL Server receives arbitrary item bytes as hexadecimal varbinary input.
     */
    public function test_sql_server_binary_writes_do_not_bind_item_bytes_as_text(): void
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')
            ->once()
            ->andReturn('sqlsrv');
        $connection->shouldReceive('getTablePrefix')
            ->once()
            ->andReturn('');
        $connection->shouldReceive('getQueryGrammar')
            ->once()
            ->andReturn(new SqlServerGrammar($connection));
        $connection->shouldReceive('update')
            ->once()
            ->with(
                'UPDATE [warehouse] SET [Items] = CONVERT(varbinary(max), ?, 2) WHERE [AccountID] = ?',
                ['FF0080C328', 'binary01'],
            )
            ->andReturn(1);
        $repository = $this->repository(Mockery::mock(DatabaseManager::class));
        $method = new ReflectionMethod($repository, 'updateWarehouseBinary');

        $method->invoke(
            $repository,
            $connection,
            'binary01',
            'Items',
            hex2bin('FF0080C328'),
        );
    }

    /**
     * Resolves and executes the production listing action.
     */
    private function createListing(
        string $accountId,
        string $characterName,
        int $vault,
        int $slot,
        int $priceCredits,
        string $idempotencyKey,
    ): MarketListing {
        return $this->app->make(CreateMarketListing::class)->execute(
            accountId: $accountId,
            characterName: $characterName,
            vault: $vault,
            slot: $slot,
            priceCredits: $priceCredits,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * Configures ownership lookup for one offline character.
     */
    private function mockOwnedOfflineCharacter(string $accountId, string $characterName): void
    {
        $reader = $this->mock(AccountReadRepository::class);
        $reader->shouldReceive('characterFor')
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
            vaults: [
                new WarehouseVault($vault, true, [$this->item($slot)]),
            ],
        );
        $repository = $this->mock(ItemStorageRepository::class);
        $repository->shouldReceive('forAccountAndCharacter')
            ->once()
            ->with($accountId, $characterName)
            ->andReturn($storage);
    }

    /**
     * Builds one catalog-enriched item while preserving the original bytes.
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
     * Creates portable Darksteam status and warehouse tables with one item.
     */
    private function configurePortableGameSchema(
        int $slot,
        bool $createStatusRow = true,
        ?int $connectStatus = 0,
    ): DatabaseManager {
        config()->set('database.connections.game', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
            'readonly' => false,
        ]);
        $database = $this->app->make(DatabaseManager::class);
        $database->purge('game');
        $connection = $database->connection('game');
        $connection->statement('CREATE TABLE MEMB_STAT (memb___id TEXT PRIMARY KEY, ConnectStat INTEGER NULL)');
        $connection->statement('CREATE TABLE warehouse (AccountID TEXT PRIMARY KEY, Items BLOB NULL)');
        $warehouse = str_repeat("\xFF", 1200);
        $warehouse = substr_replace($warehouse, $this->itemBinary, $slot * 10, 10);
        if ($createStatusRow) {
            $connection->table('MEMB_STAT')->insert([
                'memb___id' => 'seller05',
                'ConnectStat' => $connectStatus,
            ]);
        }
        $connection->table('warehouse')->insert(['AccountID' => 'seller05', 'Items' => $warehouse]);

        return $database;
    }

    /**
     * Creates the production adapter with its verified codec and catalog.
     */
    private function repository(DatabaseManager $database): SqlServer97d99iMarketItemEscrowRepository
    {
        return new SqlServer97d99iMarketItemEscrowRepository(
            database: $database,
            codec: $this->app->make(ItemCodec97d99i::class),
            catalog: $this->app->make(ItemCatalog97d99i::class),
        );
    }
}
