<?php

namespace Tests\Feature;

use App\Game\Repositories\SqlServerMuDevsSeason21MarketItemEscrowRepository;
use App\Game\Versions\MuDevsSeason21\ItemCodecAdapterMuDevsSeason21;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketEscrowResult;
use App\Market\MarketListingBlockReason;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifies exact native custody changes against disposable S21 warehouse projections.
 */
class MuDevsSeason21ItemEscrowTest extends TestCase
{
    private const FLAMEBERGE = '1A00FF01020306000000FFFEFEFEFFFFFFFFFFFFFFFFFFFFFF';

    /**
     * Installs native account and warehouse columns without live database access.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->game()->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));
        $this->game()->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-web-columns.sql')));
    }

    /**
     * Removes only the expected native item and makes extraction retries harmless.
     */
    public function test_extract_preserves_every_other_record_and_is_idempotent(): void
    {
        $item = hex2bin(self::FLAMEBERGE);
        $original = $this->seedWarehouse($item);
        $repository = $this->repository();

        $this->assertSame(MarketEscrowResult::Extracted, $repository->extract('owner', 0, 0, $item));
        $this->assertSame(substr_replace($original, str_repeat("\xFF", 25), 0, 25), $this->warehouseBinary());
        $this->assertSame(MarketEscrowResult::AlreadyExtracted, $repository->extract('owner', 0, 0, $item));
    }

    /**
     * Fits native geometry into the active page without changing reserved storage bytes.
     */
    public function test_deposit_uses_catalog_geometry_and_is_idempotent(): void
    {
        $item = hex2bin(self::FLAMEBERGE);
        $original = $this->seedWarehouse(str_repeat("\xFF", 25));
        $repository = $this->repository();

        $this->assertSame(MarketEscrowResult::Deposited, $repository->deposit('owner', $item, 1, 4));
        $this->assertSame(substr_replace($original, $item, 0, 25), $this->warehouseBinary());
        $this->assertSame(MarketEscrowResult::AlreadyDeposited, $repository->deposit('owner', $item, 1, 4));
    }

    /**
     * Rejects online or uncertain account state before changing any item bytes.
     */
    #[DataProvider('blockedOnlineStates')]
    public function test_online_or_unknown_status_cannot_extract(?int $status): void
    {
        $item = hex2bin(self::FLAMEBERGE);
        $original = $this->seedWarehouse($item);
        $this->game()->table('MEMB_STAT')->update(['ConnectStat' => $status]);

        try {
            $this->repository()->extract('owner', 0, 0, $item);
            $this->fail('Uncertain or online status must prevent a custody change.');
        } catch (MarketListingRejected $exception) {
            $this->assertSame(MarketListingBlockReason::CharacterOnline, $exception->reason);
            $this->assertSame($original, $this->warehouseBinary());
        }
    }

    /**
     * Supplies the native online and unknown status cases.
     *
     * @return iterable<string, array{int|null}>
     */
    public static function blockedOnlineStates(): iterable
    {
        yield 'online' => [1];
        yield 'unknown' => [null];
    }

    /**
     * Refuses a stale expected item and preserves the newer slot contents.
     */
    public function test_changed_item_does_not_get_extracted(): void
    {
        $item = hex2bin(self::FLAMEBERGE);
        $original = $this->seedWarehouse($item);
        $changed = substr_replace($item, "\x01", 2, 1);

        try {
            $this->repository()->extract('owner', 0, 0, $changed);
            $this->fail('A changed slot must not be extracted.');
        } catch (MarketListingRejected $exception) {
            $this->assertSame(MarketListingBlockReason::ItemChanged, $exception->reason);
            $this->assertSame($original, $this->warehouseBinary());
        }
    }

    /**
     * Keeps opaque extension state in game until its dependent persistence is implemented.
     */
    public function test_unverified_extension_data_is_not_removed(): void
    {
        $item = substr_replace(hex2bin(self::FLAMEBERGE), "\x01", 24, 1);
        $original = $this->seedWarehouse($item);

        try {
            $this->repository()->extract('owner', 0, 0, $item);
            $this->fail('Unknown native extensions must stay in game.');
        } catch (MarketListingRejected $exception) {
            $this->assertSame(MarketListingBlockReason::UnsupportedItem, $exception->reason);
            $this->assertSame($original, $this->warehouseBinary());
        }
    }

    /**
     * Prevents caller-supplied dimensions from overwriting another item's rectangle.
     */
    public function test_deposit_cannot_shrink_the_catalog_rectangle(): void
    {
        $original = $this->seedWarehouse(str_repeat("\xFF", 25));

        try {
            $this->repository()->deposit('owner', hex2bin(self::FLAMEBERGE), 1, 1);
            $this->fail('Catalog geometry must be authoritative.');
        } catch (MarketListingRejected $exception) {
            $this->assertSame(MarketListingBlockReason::UnsupportedItem, $exception->reason);
            $this->assertSame($original, $this->warehouseBinary());
        }
    }

    /**
     * Blocks special native items whose external state cannot follow a blob-only transfer.
     */
    public function test_pentagram_is_not_transferred_without_its_errtel_records(): void
    {
        $item = $this->app->make(ItemCodecAdapterMuDevsSeason21::class)->create(
            group: 12, index: 200, level: 0, durability: 255, optionLevel: 0,
            hasSkill: false, hasLuck: false, excellentMask: 0, ancientOption: 0, serial: 123,
        );
        $original = $this->seedWarehouse($item);

        try {
            $this->repository()->extract('owner', 0, 0, $item);
            $this->fail('Pentagram dependent records must not be left behind.');
        } catch (MarketListingRejected $exception) {
            $this->assertSame(MarketListingBlockReason::UnsupportedItem, $exception->reason);
            $this->assertSame($original, $this->warehouseBinary());
        }
    }

    /**
     * Keeps a full visible warehouse unchanged when no valid rectangle remains.
     */
    public function test_full_storage_rejects_deposit_without_touching_reserved_bytes(): void
    {
        $original = $this->seedWarehouse(str_repeat("\xFF", 25));
        $full = substr_replace($original, str_repeat(hex2bin(self::FLAMEBERGE), 120), 0, 3000);
        $this->game()->table('warehouse')->update(['Items' => $full]);
        $newItem = substr_replace(hex2bin(self::FLAMEBERGE), "\x07", 6, 1);

        try {
            $this->repository()->deposit('owner', $newItem, 1, 4);
            $this->fail('Full storage must reject delivery.');
        } catch (MarketListingRejected $exception) {
            $this->assertSame(MarketListingBlockReason::StorageFull, $exception->reason);
            $this->assertSame($full, $this->warehouseBinary());
        }
    }

    /**
     * Delivers only into the active account-owned extended page.
     */
    public function test_deposit_uses_the_active_extended_page(): void
    {
        $original = $this->seedWarehouse(str_repeat("\xFF", 25));
        $this->game()->table('AccountCharacter')->insert(['AccountIndex' => 1, 'Id' => 'owner', 'ExtWarehouse' => 2]);
        $this->game()->table('ExtWarehouse')->insert([
            'AccountID' => 'owner', 'AccountIndex' => 1, 'Number' => 2, 'Items' => $original, 'Money' => 0,
        ]);
        $item = hex2bin(self::FLAMEBERGE);

        $this->assertSame(MarketEscrowResult::Deposited, $this->repository()->deposit('owner', $item, 1, 4));
        $this->assertSame($original, $this->warehouseBinary());
        $this->assertSame(substr_replace($original, $item, 0, 25), $this->game()->table('ExtWarehouse')->value('Items'));
    }

    /**
     * Applies source-bound trade restrictions even when the native header is otherwise valid.
     */
    public function test_bound_equipment_cannot_enter_web_custody(): void
    {
        $item = $this->app->make(ItemCodecAdapterMuDevsSeason21::class)->create(
            group: 0, index: 48, level: 0, durability: 255, optionLevel: 0,
            hasSkill: false, hasLuck: false, excellentMask: 0, ancientOption: 0, serial: 123,
        );
        $original = $this->seedWarehouse($item);

        try {
            $this->repository()->extract('owner', 0, 0, $item);
            $this->fail('Bound equipment cannot be transferred.');
        } catch (MarketListingRejected $exception) {
            $this->assertSame(MarketListingBlockReason::UnsupportedItem, $exception->reason);
            $this->assertSame($original, $this->warehouseBinary());
        }
    }

    /**
     * Seeds one offline account and keeps a distinct sentinel in reserved storage.
     */
    private function seedWarehouse(string $item): string
    {
        $binary = substr_replace(str_repeat("\xFF", 6000), $item, 0, 25);
        $binary = substr_replace($binary, str_repeat("\xA5", 25), 130 * 25, 25);
        $this->game()->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 0]);
        $this->game()->table('warehouse')->insert([
            'AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => $binary, 'Money' => 100,
        ]);

        return $binary;
    }

    /**
     * Returns the isolated owned warehouse's complete native bytes.
     */
    private function warehouseBinary(): string
    {
        return $this->game()->table('warehouse')->where('AccountID', 'owner')->value('Items');
    }

    /**
     * Resolves the native adapter without enabling any website mutation route.
     */
    private function repository(): SqlServerMuDevsSeason21MarketItemEscrowRepository
    {
        return $this->app->make(SqlServerMuDevsSeason21MarketItemEscrowRepository::class);
    }

    /**
     * Returns the network-isolated native game database projection.
     */
    private function game(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
