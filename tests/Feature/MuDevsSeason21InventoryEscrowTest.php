<?php

namespace Tests\Feature;

use App\Game\Data\ExpectedInventoryItem;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterInventoryEscrowRepository;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketEscrowResult;
use App\Market\MarketListingBlockReason;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Verifies native bag geometry, equipment retention and atomic inventory custody receipts.
 */
final class MuDevsSeason21InventoryEscrowTest extends TestCase
{
    private const FLAMEBERGE = '1A00FF01020306000000FFFEFEFEFFFFFFFFFFFFFFFFFFFFFF';

    private const CREATION = '160001010203010000E000FFFFFFFFFFFFFFFFFFFFFFFFFFFF';

    /**
     * Creates the complete disposable native inventory and receipt projection.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->game()->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));
        $this->game()->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-web-columns.sql')));
    }

    /**
     * Extracts from an unlocked extension without changing equipment or reserved state.
     */
    public function test_extension_extraction_preserves_the_complete_239_record_inventory(): void
    {
        $item = hex2bin(self::FLAMEBERGE);
        $original = $this->seedInventory([70 => $item], 1);
        $repository = $this->repository();

        self::assertSame(MarketEscrowResult::Extracted, $repository->extractForOperation('owner', 'Hero', 70, $item, __FUNCTION__));
        self::assertSame(substr_replace($original, str_repeat("\xFF", 25), (12 + 70) * 25, 25), $this->inventory());
        self::assertSame(MarketEscrowResult::AlreadyExtracted, $repository->extractForOperation('owner', 'Hero', 70, $item, __FUNCTION__));
        self::assertSame(1, $this->game()->table('IWEB_S21_ItemOperation')->count());
    }

    /**
     * Keeps locked bag cells outside the current extension boundary untouched.
     */
    public function test_locked_bag_extension_cannot_be_extracted(): void
    {
        $item = hex2bin(self::FLAMEBERGE);
        $original = $this->seedInventory([110 => $item], 1);

        try {
            $this->repository()->extractForOperation('owner', 'Hero', 110, $item, __FUNCTION__);
            self::fail('Locked inventory cells must not enter custody.');
        } catch (MarketListingRejected $exception) {
            self::assertSame(MarketListingBlockReason::ItemNotFound, $exception->reason);
            self::assertSame($original, $this->inventory());
        }
    }

    /**
     * Places a four-cell weapon wholly inside one unlocked inventory page.
     */
    public function test_deposit_does_not_span_main_and_extension_pages(): void
    {
        $jewels = array_fill(0, 48, hex2bin(self::CREATION));
        $original = $this->seedInventory($jewels, 1);
        $item = hex2bin(self::FLAMEBERGE);

        self::assertSame(MarketEscrowResult::Deposited, $this->repository()->depositForOperation('owner', 'Hero', $item, 1, 4, __FUNCTION__));
        self::assertSame(substr_replace($original, $item, (12 + 64) * 25, 25), $this->inventory());
    }

    /**
     * Aborts the entire batch if one of its expected records changed.
     */
    public function test_stale_batch_keeps_all_items_and_creates_no_receipt(): void
    {
        $sword = hex2bin(self::FLAMEBERGE);
        $jewel = hex2bin(self::CREATION);
        $original = $this->seedInventory([0 => $sword, 1 => $jewel]);
        $expected = [new ExpectedInventoryItem(0, $sword), new ExpectedInventoryItem(1, substr_replace($jewel, "\x02", 2, 1))];

        try {
            $this->repository()->extractManyForOperation('owner', 'Hero', $expected, __FUNCTION__);
            self::fail('A stale batch must fail atomically.');
        } catch (MarketListingRejected $exception) {
            self::assertSame(MarketListingBlockReason::ItemChanged, $exception->reason);
            self::assertSame($original, $this->inventory());
            self::assertSame(0, $this->game()->table('IWEB_S21_ItemOperation')->count());
        }
    }

    /**
     * Acknowledges the same committed batch regardless of input order.
     */
    public function test_complete_batch_is_journaled_once_and_replays_without_more_removals(): void
    {
        $sword = hex2bin(self::FLAMEBERGE);
        $jewel = hex2bin(self::CREATION);
        $this->seedInventory([0 => $sword, 1 => $jewel]);
        $expected = [new ExpectedInventoryItem(0, $sword), new ExpectedInventoryItem(1, $jewel)];

        self::assertSame(MarketEscrowResult::Extracted, $this->repository()->extractManyForOperation('owner', 'Hero', $expected, __FUNCTION__));
        $after = $this->inventory();
        self::assertSame(MarketEscrowResult::AlreadyExtracted, $this->repository()->extractManyForOperation('owner', 'Hero', array_reverse($expected), __FUNCTION__));
        self::assertSame($after, $this->inventory());
        self::assertSame(1, $this->game()->table('IWEB_S21_ItemOperation')->count());
    }

    /**
     * Rejects native inventory writes while the account is online.
     */
    public function test_online_account_cannot_change_inventory_or_receipts(): void
    {
        $item = hex2bin(self::FLAMEBERGE);
        $original = $this->seedInventory([0 => $item]);
        $this->game()->table('MEMB_STAT')->update(['ConnectStat' => 1]);

        try {
            $this->repository()->extractForOperation('owner', 'Hero', 0, $item, __FUNCTION__);
            self::fail('Online account inventory must not be changed.');
        } catch (MarketListingRejected $exception) {
            self::assertSame(MarketListingBlockReason::CharacterOnline, $exception->reason);
            self::assertSame($original, $this->inventory());
            self::assertSame(0, $this->game()->table('IWEB_S21_ItemOperation')->count());
        }
    }

    /**
     * Seeds an owned bag while retaining distinct bytes in every additional equipment slot.
     *
     * @param  array<int, string>  $items
     */
    private function seedInventory(array $items, int $extensions = 0): string
    {
        $binary = str_repeat("\xFF", 5975);
        $binary = substr_replace($binary, hex2bin(self::FLAMEBERGE), 0, 25);
        $binary = substr_replace($binary, str_repeat("\xA5", 75), 236 * 25, 75);

        foreach ($items as $slot => $item) {
            $binary = substr_replace($binary, $item, (12 + $slot) * 25, 25);
        }

        $this->game()->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 0]);
        $this->game()->table('Character')->insert([
            'AccountID' => 'owner', 'Name' => 'Hero', 'CharacterIndex' => 42,
            'Inventory' => $binary, 'ExtInventory' => $extensions,
        ]);

        return $binary;
    }

    /**
     * Returns the complete native inventory for exact-byte assertions.
     */
    private function inventory(): string
    {
        return $this->game()->table('Character')->where('Name', 'Hero')->value('Inventory');
    }

    /**
     * Resolves the native inventory adapter independently of public route availability.
     */
    private function repository(): SqlServerMuDevsSeason21CharacterInventoryEscrowRepository
    {
        return $this->app->make(SqlServerMuDevsSeason21CharacterInventoryEscrowRepository::class);
    }

    /**
     * Returns the isolated game projection installed by the feature test base.
     */
    private function game(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
