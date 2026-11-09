<?php

namespace Tests\Feature;

use App\Game\Repositories\SqlServerMuEmuSeason6MarketItemEscrowRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6ZenBankRepository;
use App\Game\Versions\MuEmuSeason6\ItemCatalogMuEmuSeason6;
use App\Game\Versions\MuEmuSeason6\ItemCodecMuEmuSeason6;
use App\Market\MarketEscrowResult;
use App\ZenBank\ZenProjectionResult;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Verifies MuEMU warehouse writes stay inside the active player-visible page.
 */
final class MuEmuSeason6WarehouseMutationRepositoryTest extends TestCase
{
    private const SACRED_GLOVE = '20DD41FFFFFE3958000000FFFFFFFFFF';

    private const KRIS = '003F14FFFFFE3C0000005DFFFFFFFFFF';

    /**
     * Creates the consumed Update 32 warehouse schema and one offline account.
     */
    protected function setUp(): void
    {
        parent::setUp();

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
        $connection->statement('CREATE TABLE Warehouse (AccountID TEXT PRIMARY KEY, Items BLOB NULL, Money INTEGER NULL)');
        $connection->statement('CREATE TABLE ExtWarehouse (AccountID TEXT NOT NULL, Items BLOB NULL, Money INTEGER NULL, Number INTEGER NULL)');
        $connection->statement('CREATE TABLE AccountCharacter (Id TEXT PRIMARY KEY, ExtWarehouse INTEGER NOT NULL DEFAULT 0)');
        $connection->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 0]);
        $connection->table('AccountCharacter')->insert(['Id' => 'owner', 'ExtWarehouse' => 1]);
        $connection->table('Warehouse')->insert([
            'AccountID' => 'owner',
            'Items' => $this->putItem($this->emptyWarehouse(), 200, self::KRIS),
            'Money' => 10_000,
        ]);
        $connection->table('ExtWarehouse')->insert([
            'AccountID' => 'owner',
            'Items' => $this->putItem(
                $this->putItem($this->emptyWarehouse(), 119, self::SACRED_GLOVE),
                180,
                self::KRIS,
            ),
            'Money' => 25_000,
            'Number' => 1,
        ]);
    }

    /**
     * Confirms the last visible cell can be extracted without truncating reserved bytes.
     */
    public function test_extracts_from_last_visible_extended_warehouse_cell(): void
    {
        $item = hex2bin(self::SACRED_GLOVE);

        self::assertIsString($item);

        $result = $this->escrowRepository()->extract('owner', 1, 119, $item);
        $extended = $this->game()
            ->table('ExtWarehouse')
            ->where('AccountID', 'owner')
            ->where('Number', 1)
            ->value('Items');

        self::assertSame(MarketEscrowResult::Extracted, $result);
        self::assertIsString($extended);
        self::assertSame(3_840, strlen($extended));
        self::assertSame(str_repeat("\xFF", 16), substr($extended, 119 * 16, 16));
        self::assertSame(hex2bin(self::KRIS), substr($extended, 180 * 16, 16));
    }

    /**
     * Confirms returns target the active extended page and preserve both native blobs.
     */
    public function test_deposits_into_active_extended_warehouse_only(): void
    {
        $item = hex2bin(self::KRIS);

        self::assertIsString($item);

        $mainBefore = $this->game()->table('Warehouse')->value('Items');
        $result = $this->escrowRepository()->deposit('owner', $item, 1, 1);
        $mainAfter = $this->game()->table('Warehouse')->value('Items');
        $extended = $this->game()->table('ExtWarehouse')->value('Items');

        self::assertSame(MarketEscrowResult::Deposited, $result);
        self::assertSame($mainBefore, $mainAfter);
        self::assertIsString($extended);
        self::assertSame(3_840, strlen($extended));
        self::assertSame($item, substr($extended, 0, 16));
        self::assertSame($item, substr($extended, 180 * 16, 16));
    }

    /**
     * Confirms Zen reads and replay-safe writes use the currently selected page.
     */
    public function test_projects_zen_into_active_extended_warehouse(): void
    {
        $repository = new SqlServerMuEmuSeason6ZenBankRepository(
            $this->app->make(DatabaseManager::class),
        );

        self::assertSame(25_000, $repository->currentBalance('owner'));
        self::assertSame(
            ZenProjectionResult::Applied,
            $repository->project('owner', 25_000, 30_000),
        );
        self::assertSame(10_000, (int) $this->game()->table('Warehouse')->value('Money'));
        self::assertSame(30_000, (int) $this->game()->table('ExtWarehouse')->value('Money'));
    }

    /**
     * Returns the MuEMU market custody adapter.
     */
    private function escrowRepository(): SqlServerMuEmuSeason6MarketItemEscrowRepository
    {
        return new SqlServerMuEmuSeason6MarketItemEscrowRepository(
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
     * Builds one empty native 240-slot warehouse blob.
     */
    private function emptyWarehouse(): string
    {
        return str_repeat("\xFF", 240 * ItemCodecMuEmuSeason6::SLOT_BYTES);
    }

    /**
     * Places one exact sixteen-byte item in a native warehouse slot.
     */
    private function putItem(string $warehouse, int $slot, string $hex): string
    {
        $item = hex2bin($hex);

        self::assertIsString($item);

        return substr_replace(
            $warehouse,
            $item,
            $slot * ItemCodecMuEmuSeason6::SLOT_BYTES,
            ItemCodecMuEmuSeason6::SLOT_BYTES,
        );
    }
}
