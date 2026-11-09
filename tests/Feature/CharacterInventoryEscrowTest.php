<?php

namespace Tests\Feature;

use App\Game\Data\ExpectedInventoryItem;
use App\Game\Repositories\SqlServer97d99iCharacterInventoryEscrowRepository;
use App\Game\Versions\Version97d99i\ItemCatalog97d99i;
use App\Game\Versions\Version97d99i\ItemCodec97d99i;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketEscrowResult;
use App\Market\MarketListingBlockReason;
use Illuminate\Database\DatabaseManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifies character inventory escrow cannot race a connected game account.
 */
final class CharacterInventoryEscrowTest extends TestCase
{
    private const ACCOUNT_ID = 'inventory1';

    private const CHARACTER_NAME = 'InventoryHero';

    private const BAG_SLOT = 5;

    private string $itemBinary;

    /**
     * Prepares one deterministic 97d+99i item for exact-byte assertions.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->itemBinary = "\x00\x00\x20\x01\x02\x03\x04\x00\x00\x00";
    }

    /**
     * Confirms an explicitly offline account can move an exact inventory item.
     */
    public function test_explicitly_offline_account_can_extract_inventory_item(): void
    {
        $database = $this->configureGameSchema(connectStatus: 0);
        $repository = $this->repository($database);

        $result = $repository->extract(
            self::ACCOUNT_ID,
            self::CHARACTER_NAME,
            self::BAG_SLOT,
            $this->itemBinary,
        );
        $inventory = $database->connection('game')->table('Character')->value('Inventory');

        $this->assertSame(MarketEscrowResult::Extracted, $result);
        $this->assertSame(
            str_repeat("\xFF", ItemCodec97d99i::SLOT_BYTES),
            substr($inventory, $this->itemOffset(), ItemCodec97d99i::SLOT_BYTES),
        );
    }

    /**
     * Confirms an exact batch is removed in one write and supports safe replay.
     */
    public function test_offline_account_can_extract_an_exact_inventory_batch_atomically(): void
    {
        $database = $this->configureGameSchema(connectStatus: 0);
        $connection = $database->connection('game');
        $secondBinary = "\x01\x00\x20\x05\x06\x07\x08\x00\x00\x00";
        $inventory = (string) $connection->table('Character')->value('Inventory');
        $connection->table('Character')->update([
            'Inventory' => substr_replace(
                $inventory,
                $secondBinary,
                $this->itemOffset(6),
                ItemCodec97d99i::SLOT_BYTES,
            ),
        ]);
        $repository = $this->repository($database);
        $expectedItems = [
            new ExpectedInventoryItem(self::BAG_SLOT, $this->itemBinary),
            new ExpectedInventoryItem(6, $secondBinary),
        ];

        $result = $repository->extractMany(
            self::ACCOUNT_ID,
            self::CHARACTER_NAME,
            $expectedItems,
        );
        $replayResult = $repository->extractMany(
            self::ACCOUNT_ID,
            self::CHARACTER_NAME,
            $expectedItems,
        );
        $updatedInventory = (string) $connection->table('Character')->value('Inventory');

        $this->assertSame(MarketEscrowResult::Extracted, $result);
        $this->assertSame(MarketEscrowResult::AlreadyExtracted, $replayResult);
        $this->assertSame(str_repeat("\xFF", 10), substr($updatedInventory, $this->itemOffset(), 10));
        $this->assertSame(str_repeat("\xFF", 10), substr($updatedInventory, $this->itemOffset(6), 10));
    }

    /**
     * Confirms online and unknown statuses leave character inventory unchanged.
     */
    #[DataProvider('unsafeConnectionStatuses')]
    public function test_unverified_account_status_rejects_inventory_mutation(
        bool $createStatusRow,
        ?int $connectStatus,
    ): void {
        $database = $this->configureGameSchema($createStatusRow, $connectStatus);
        $repository = $this->repository($database);
        $beforeInventory = $database->connection('game')->table('Character')->value('Inventory');

        try {
            $repository->extract(
                self::ACCOUNT_ID,
                self::CHARACTER_NAME,
                self::BAG_SLOT,
                $this->itemBinary,
            );
            $this->fail('An account without explicit offline status must not mutate inventory.');
        } catch (MarketListingRejected $exception) {
            $this->assertSame(MarketListingBlockReason::CharacterOnline, $exception->reason);
        }

        $afterInventory = $database->connection('game')->table('Character')->value('Inventory');

        $this->assertSame($beforeInventory, $afterInventory);
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
     * Creates isolated connection, character and inventory records.
     */
    private function configureGameSchema(
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
        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, Inventory BLOB NULL)');

        if ($createStatusRow) {
            $connection->table('MEMB_STAT')->insert([
                'memb___id' => self::ACCOUNT_ID,
                'ConnectStat' => $connectStatus,
            ]);
        }

        $inventory = str_repeat("\xFF", 76 * ItemCodec97d99i::SLOT_BYTES);
        $inventory = substr_replace(
            $inventory,
            $this->itemBinary,
            $this->itemOffset(),
            ItemCodec97d99i::SLOT_BYTES,
        );
        $connection->table('Character')->insert([
            'AccountID' => self::ACCOUNT_ID,
            'Name' => self::CHARACTER_NAME,
            'Inventory' => $inventory,
        ]);

        return $database;
    }

    /**
     * Resolves the byte offset for one bag slot after twelve equipment slots.
     */
    private function itemOffset(int $slot = self::BAG_SLOT): int
    {
        return (12 + $slot) * ItemCodec97d99i::SLOT_BYTES;
    }

    /**
     * Creates the production inventory adapter with its verified codec and catalog.
     */
    private function repository(
        DatabaseManager $database,
    ): SqlServer97d99iCharacterInventoryEscrowRepository {
        return new SqlServer97d99iCharacterInventoryEscrowRepository(
            database: $database,
            codec: $this->app->make(ItemCodec97d99i::class),
            catalog: $this->app->make(ItemCatalog97d99i::class),
        );
    }
}
