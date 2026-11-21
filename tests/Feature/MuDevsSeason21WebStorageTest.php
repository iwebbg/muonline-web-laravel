<?php

namespace Tests\Feature;

use App\Game\Contracts\RealmReadRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Models\WebStorageItem;
use App\Modules\ModuleManager;
use App\Providers\AppServiceProvider;
use App\WebStorage\Actions\StoreWebStorageItem;
use App\WebStorage\Actions\WithdrawWebStorageItem;
use App\WebStorage\ItemStorageLocation;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Exercises exact S21 custody through the real application actions and native adapters.
 */
final class MuDevsSeason21WebStorageTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Installs only disposable native projections and selects their profile bindings.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.profile.key', 'mudevs_season_21');
        config()->set('game.data_source', 'database');
        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);
        $this->app->forgetInstance(GameVersionProfile::class);
        $this->app->forgetInstance(ModuleManager::class);
        $this->app->forgetInstance(RealmReadRepository::class);
        (new AppServiceProvider($this->app))->register();
        $game = $this->app->make(DatabaseManager::class)->connection('game');
        $game->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));
        $game->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-web-columns.sql')));
    }

    /**
     * Moves both source locations into custody and back without changing the record.
     */
    #[DataProvider('sourceLocations')]
    public function test_store_and_withdraw_preserve_native_bytes_and_create_one_receipt_per_direction(ItemStorageLocation $source, string $hex): void
    {
        $game = $this->app->make(DatabaseManager::class)->connection('game');
        $binary = hex2bin($hex);
        $emptyWarehouse = str_repeat("\xFF", 6000);
        $emptyInventory = str_repeat("\xFF", 5975);
        $warehouse = $source === ItemStorageLocation::Warehouse ? substr_replace($emptyWarehouse, $binary, 0, 25) : $emptyWarehouse;
        $inventory = $source === ItemStorageLocation::Inventory ? substr_replace($emptyInventory, $binary, 12 * 25, 25) : $emptyInventory;
        $game->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 0]);
        $game->table('Character')->insert([
            'AccountID' => 'owner', 'Name' => 'Hero', 'CharacterIndex' => 1, 'Class' => 240,
            'Inventory' => $inventory, 'ExtInventory' => 0,
        ]);
        $game->table('warehouse')->insert(['AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => $warehouse]);
        $key = (string) Str::uuid();
        $store = $this->app->make(StoreWebStorageItem::class);

        $item = $store->execute('owner', 'Hero', 0, 0, $key, $source);

        self::assertSame(WebStorageItemStatus::Available, $item->status);
        self::assertSame($binary, $item->item_binary);
        self::assertSame($emptyWarehouse, $game->table('warehouse')->value('Items'));
        self::assertSame($emptyInventory, $game->table('Character')->value('Inventory'));
        self::assertSame($item->getKey(), $store->execute('owner', 'Hero', 0, 0, $key, $source)->getKey());
        self::assertSame(1, WebStorageItem::query()->count());
        self::assertSame(1, $game->table('IWEB_S21_ItemOperation')->count());

        $withdraw = $this->app->make(WithdrawWebStorageItem::class);
        $withdrawn = $withdraw->execute('owner', (int) $item->getKey());

        self::assertSame(WebStorageItemStatus::Withdrawn, $withdrawn->status);
        self::assertSame(substr_replace($emptyWarehouse, $binary, 0, 25), $game->table('warehouse')->value('Items'));
        self::assertSame(2, $game->table('IWEB_S21_ItemOperation')->count());
        $withdraw->execute('owner', (int) $item->getKey());
        self::assertSame(2, $game->table('IWEB_S21_ItemOperation')->count());
    }

    /**
     * Covers both extraction boundaries with socket gear and independently captured game-created wings.
     *
     * @return array<string, array{ItemStorageLocation, string}>
     */
    public static function sourceLocations(): array
    {
        $flameberge = '1A00FF01020306000000FFFEFEFEFFFFFFFFFFFFFFFFFFFFFF';
        $wingOfStorm = '247FFF0000003D4000C000FFFFFFFFFFFFFFFFFFFFFFFFFFFF';

        return [
            'warehouse' => [ItemStorageLocation::Warehouse, $flameberge],
            'inventory' => [ItemStorageLocation::Inventory, $flameberge],
            'wing warehouse' => [ItemStorageLocation::Warehouse, $wingOfStorm],
            'wing inventory' => [ItemStorageLocation::Inventory, $wingOfStorm],
        ];
    }
}
