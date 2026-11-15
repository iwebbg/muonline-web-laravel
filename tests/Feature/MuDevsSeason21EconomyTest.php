<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\CreditEarning\Actions\ExchangeItemsForCredits;
use App\CreditEarning\CreditExchangeStatus;
use App\CreditEarning\ItemExchangeMatcher;
use App\Game\Contracts\ItemCodec;
use App\Game\Contracts\ItemSerialRepository;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\JewelBank\Actions\StoreJewelBatch;
use App\JewelBank\Actions\WithdrawJewelBatch;
use App\Market\Actions\CreateMarketListing;
use App\Market\Actions\PlaceAuctionBid;
use App\Market\Actions\PurchaseMarketListing;
use App\Market\Actions\SettleExpiredAuction;
use App\Market\MarketListingStatus;
use App\Market\MarketSaleType;
use App\Models\AccountWallet;
use App\Models\Administrator;
use App\Models\CreditExchangeOffer;
use App\Models\EconomySetting;
use App\Models\ShopOffer;
use App\Models\ShopOfferItem;
use App\Models\WalletTransaction;
use App\Models\WebStorageItem;
use App\Modules\ModuleManager;
use App\Providers\AppServiceProvider;
use App\Shop\AccountRewardStatus;
use App\Shop\Actions\PurchaseShopOffer;
use App\Shop\EconomySettingReader;
use App\Shop\Exceptions\ShopPurchaseRejected;
use App\Shop\ShopPurchaseBlockReason;
use App\WebStorage\ItemStorageLocation;
use App\WebStorage\WebStorageContext;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifies native item sales and rewards while keeping web Credits separate from game coins.
 */
final class MuDevsSeason21EconomyTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const FLAMEBERGE = '1A00FF01020306000000FFFEFEFEFFFFFFFFFFFFFFFFFFFFFF';

    /**
     * Selects native adapters against isolated projections and permits test-only writes.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.profile.key', 'mudevs_season_21');
        config()->set('game.profile.version', 'S21.1-2');
        config()->set('game.data_source', 'database');
        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);
        $this->app->forgetInstance(GameVersionProfile::class);
        $this->app->forgetInstance(ModuleManager::class);
        $this->app->forgetInstance(RealmReadRepository::class);
        (new AppServiceProvider($this->app))->register();
        $this->game()->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));
        $this->game()->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-web-columns.sql')));
    }

    /**
     * Sells the exact original record once and moves only application Credits.
     */
    public function test_native_market_sale_delivers_once_without_changing_game_coins(): void
    {
        $this->seedAccount('owner', 1, hex2bin(self::FLAMEBERGE));
        $this->seedAccount('buyer', 2);
        AccountWallet::factory()->create(['account_id' => 'buyer', 'balance' => 500]);
        $listing = $this->app->make(CreateMarketListing::class)->execute('owner', 'ownerHero', 0, 0, 125, (string) Str::uuid());
        self::assertSame(MarketListingStatus::Active, $listing->status);
        $purchase = $this->app->make(PurchaseMarketListing::class);
        $key = (string) Str::uuid();

        $sold = $purchase->execute('buyer', (int) $listing->getKey(), $key);
        $purchase->execute('buyer', (int) $listing->getKey(), $key);

        self::assertSame(MarketListingStatus::Delivered, $sold->status);
        self::assertSame(str_repeat("\xFF", 6000), $this->warehouse('owner'));
        self::assertSame(hex2bin(self::FLAMEBERGE).str_repeat("\xFF", 5975), $this->warehouse('buyer'));
        self::assertSame(375, AccountWallet::query()->where('account_id', 'buyer')->value('balance'));
        self::assertSame(125, AccountWallet::query()->where('account_id', 'owner')->value('balance'));
        self::assertSame(2, WalletTransaction::query()->count());
        self::assertSame(2, $this->game()->table('IWEB_S21_ItemOperation')->count());
        self::assertSame(100, (int) $this->game()->table('CashShopData')->where('AccountID', 'owner')->value('WCoinC'));
        self::assertSame(200, (int) $this->game()->table('CashShopData')->where('AccountID', 'buyer')->value('WCoinC'));
    }

    /**
     * Settles a native auction through the same receipt-protected delivery boundary.
     */
    public function test_native_auction_settlement_delivers_the_winning_item_once(): void
    {
        $this->seedAccount('owner', 1, hex2bin(self::FLAMEBERGE));
        $this->seedAccount('buyer', 2);
        AccountWallet::factory()->create(['account_id' => 'buyer', 'balance' => 500]);
        $listing = $this->app->make(CreateMarketListing::class)->execute('owner', 'ownerHero', 0, 0, 100, (string) Str::uuid(), MarketSaleType::Auction, 10);
        $this->app->make(PlaceAuctionBid::class)->execute('buyer', (int) $listing->getKey(), 120, (string) Str::uuid());
        $listing->update(['expires_at' => now()->subMinute()]);
        $settle = $this->app->make(SettleExpiredAuction::class);

        $settle->execute((int) $listing->getKey());
        $settle->execute((int) $listing->getKey());

        self::assertSame(MarketListingStatus::Delivered, $listing->refresh()->status);
        self::assertSame(380, AccountWallet::query()->where('account_id', 'buyer')->value('balance'));
        self::assertSame(120, AccountWallet::query()->where('account_id', 'owner')->value('balance'));
        self::assertSame(hex2bin(self::FLAMEBERGE).str_repeat("\xFF", 5975), $this->warehouse('buyer'));
        self::assertSame(2, $this->game()->table('IWEB_S21_ItemOperation')->count());
    }

    /**
     * Delivers an operator-priced native shop item with one serial and one charge.
     */
    public function test_native_shop_purchase_reserves_and_delivers_one_exact_record(): void
    {
        $this->seedAccount('buyer', 2);
        AccountWallet::factory()->create(['account_id' => 'buyer', 'balance' => 500]);
        $offer = ShopOffer::factory()->create(['price_credits' => 100]);
        ShopOfferItem::factory()->for($offer, 'offer')->create();
        $this->mock(ItemSerialRepository::class)->shouldReceive('nextSerial')->once()->andReturn(0x01020309);
        $purchase = $this->app->make(PurchaseShopOffer::class);
        $key = (string) Str::uuid();

        $reward = $purchase->execute('buyer', $offer, $key);
        $purchase->execute('buyer', $offer, $key);

        self::assertSame(AccountRewardStatus::Claimed, $reward->status);
        self::assertSame(hex2bin('0000FF0102030900000000FFFFFFFFFFFFFFFFFFFFFFFFFFFF'), $reward->item_binary);
        self::assertSame($reward->item_binary.str_repeat("\xFF", 5975), $this->warehouse('buyer'));
        self::assertSame(400, AccountWallet::query()->where('account_id', 'buyer')->value('balance'));
        self::assertSame(1, WalletTransaction::query()->count());
        self::assertSame(1, $this->game()->table('IWEB_S21_ItemOperation')->count());
    }

    /**
     * Rejects stale unsupported offers before charging or reserving serials.
     */
    public function test_unsupported_native_shop_offer_does_not_charge_credits(): void
    {
        $this->seedAccount('buyer', 2);
        AccountWallet::factory()->create(['account_id' => 'buyer', 'balance' => 500]);
        $offer = ShopOffer::factory()->create();
        ShopOfferItem::factory()->for($offer, 'offer')->create(['item_code' => '12:0']);
        $this->mock(ItemSerialRepository::class)->shouldNotReceive('nextSerial');

        try {
            $this->app->make(PurchaseShopOffer::class)->execute('buyer', $offer, (string) Str::uuid());
            self::fail('Unsupported native items must not be sold.');
        } catch (ShopPurchaseRejected $exception) {
            self::assertSame(ShopPurchaseBlockReason::OfferUnavailable, $exception->reason);
            self::assertSame(500, AccountWallet::query()->where('account_id', 'buyer')->value('balance'));
            self::assertSame(0, WalletTransaction::query()->count());
        }
    }

    /**
     * Preserves the prepared reward in Web Vault when the native account is online.
     */
    public function test_online_native_shop_delivery_falls_back_to_owned_web_storage(): void
    {
        $this->seedAccount('buyer', 2);
        $this->game()->table('MEMB_STAT')->update(['ConnectStat' => 1]);
        AccountWallet::factory()->create(['account_id' => 'buyer', 'balance' => 500]);
        $offer = ShopOffer::factory()->create();
        ShopOfferItem::factory()->for($offer, 'offer')->create();
        $this->mock(ItemSerialRepository::class)->shouldReceive('nextSerial')->once()->andReturn(123);

        $reward = $this->app->make(PurchaseShopOffer::class)->execute('buyer', $offer, (string) Str::uuid());

        self::assertSame(AccountRewardStatus::Claimed, $reward->status);
        self::assertSame($reward->item_binary, WebStorageItem::query()->sole()->item_binary);
        self::assertSame('buyer', WebStorageItem::query()->sole()->account_id);
        self::assertSame(str_repeat("\xFF", 6000), $this->warehouse('buyer'));
        self::assertSame(0, $this->game()->table('IWEB_S21_ItemOperation')->count());
    }

    /**
     * Renders restored native menus without resolving any legacy game repository.
     */
    #[DataProvider('economyPages')]
    public function test_native_economy_pages_are_available(string $path): void
    {
        $this->seedAccount('owner', 1);
        $this->actingAs(new GameAccount(1, 'owner', 'Owner', null, false, false, ''))->get($path)->assertOk();
    }

    /**
     * Lists supported pages without exposing unimplemented VIP or generated pricing.
     *
     * @return array<string, array{string}>
     */
    public static function economyPages(): array
    {
        return [
            'market' => ['/market'], 'credits' => ['/account/credits'],
            'web vault' => ['/account?tab=vaults'], 'shop' => ['/account/shop/items'],
            'mystery boxes' => ['/account/shop/mystery-boxes'], 'rewards' => ['/account/rewards'],
            'votes' => ['/account/votes'], 'jewel bank' => ['/account/jewel-bank'],
        ];
    }

    /**
     * Keeps unreviewed identity services hidden while exposing journaled native progression purchases.
     */
    public function test_paid_character_services_stay_hidden_when_the_native_route_is_unavailable(): void
    {
        $this->seedAccount('owner', 1);
        EconomySetting::factory()->create([
            'character_rename_enabled' => true, 'character_class_change_enabled' => true,
            'character_reset_purchase_enabled' => true, 'character_level_up_points_purchase_enabled' => true,
            'item_exchange_enabled' => true,
        ]);
        $settings = $this->app->make(EconomySettingReader::class)->current();

        self::assertFalse($settings->characterRenameEnabled);
        self::assertFalse($settings->characterClassChangeEnabled);
        self::assertTrue($settings->characterResetPurchaseEnabled);
        self::assertTrue($settings->characterLevelUpPointsPurchaseEnabled);
        self::assertTrue($settings->itemExchangeEnabled);
        $this->actingAs(new GameAccount(1, 'owner', 'Owner', null, false, false, ''))->get('/account')
            ->assertOk()->assertViewHas('characterServicesEnabled', true);
    }

    /**
     * Credits an exact native inventory batch once while retaining every equipment record.
     */
    public function test_native_item_exchange_uses_one_atomic_batch_receipt_and_one_credit_award(): void
    {
        $this->seedAccount('owner', 1);
        EconomySetting::factory()->create(['item_exchange_enabled' => true]);
        $item = hex2bin('0000FF0102030900000000FFFFFFFFFFFFFFFFFFFFFFFFFFFF');
        $inventory = str_repeat("\xFF", 5975);
        $inventory = substr_replace($inventory, $item, 12 * 25, 25);
        $this->game()->table('Character')->where('AccountID', 'owner')->update(['Inventory' => $inventory]);
        $offer = CreditExchangeOffer::factory()->create([
            'item_group' => 0, 'item_index' => 0, 'level' => 0, 'option_level' => 0,
            'has_skill' => false, 'has_luck' => false, 'excellent_mask' => 0, 'ancient_option' => 0,
        ]);
        $action = $this->app->make(ExchangeItemsForCredits::class);
        $key = (string) Str::uuid();

        $result = $action->execute('owner', 'ownerHero', $offer->public_id, 1, $key);
        $action->execute('owner', 'ownerHero', $offer->public_id, 1, $key);

        self::assertSame(CreditExchangeStatus::Completed, $result->status);
        self::assertSame(str_repeat("\xFF", 5975), $this->game()->table('Character')->value('Inventory'));
        self::assertSame(50, AccountWallet::query()->where('account_id', 'owner')->value('balance'));
        self::assertSame(1, WalletTransaction::query()->count());
        self::assertSame(1, $this->game()->table('IWEB_S21_ItemOperation')->count());
        $advanced = $this->app->make(ItemCodec::class)->decode(substr_replace($item, "\x11", 10, 1), 1)[0];
        self::assertFalse($this->app->make(ItemExchangeMatcher::class)->matches($advanced, $offer));
    }

    /**
     * Renders each native economy administration screen with the adapter's option metadata.
     */
    #[DataProvider('administrationPages')]
    public function test_native_economy_administration_pages_are_available(string $route): void
    {
        $this->actingAs(Administrator::factory()->create(), 'admin')->get(route($route))->assertOk();
    }

    /**
     * Lists the reviewed administration pages that do not need native VIP semantics.
     *
     * @return array<string, array{string}>
     */
    public static function administrationPages(): array
    {
        return [
            'wallet' => ['admin.wallet.index'], 'settings' => ['admin.economy-settings.edit'],
            'offers' => ['admin.shop-offers.index'], 'new offer' => ['admin.shop-offers.create'],
            'market' => ['admin.market.index'], 'recovery' => ['admin.economy-recovery.index'],
            'vote sites' => ['admin.vote-sites.index'], 'packages' => ['admin.credit-packages.index'],
        ];
    }

    /**
     * Keeps a complete native jewel record intact through grouped bank custody and return.
     */
    public function test_native_jewel_bank_moves_whole_physical_records_without_splitting(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-creation-jewel.json')), true, flags: JSON_THROW_ON_ERROR);
        $binary = hex2bin($fixture['hex']);
        $this->seedAccount('owner', 1, $binary);
        $stored = $this->app->make(StoreJewelBatch::class)->execute(
            'owner', 'ownerHero', 0, ItemStorageLocation::Warehouse,
            '14:22', 0, 1, (string) Str::uuid(),
        );

        self::assertCount(1, $stored);
        self::assertSame(WebStorageContext::JewelBank, $stored[0]->storage_context);
        self::assertSame($binary, $stored[0]->item_binary);
        self::assertSame(str_repeat("\xFF", 6000), $this->warehouse('owner'));
        $withdrawn = $this->app->make(WithdrawJewelBatch::class)->execute(
            'owner', '14:22', 0, 1, ItemStorageLocation::Warehouse, null,
        );
        self::assertCount(1, $withdrawn);
        self::assertSame(WebStorageItemStatus::Withdrawn, $withdrawn[0]->status);
        self::assertSame($binary.str_repeat("\xFF", 5975), $this->warehouse('owner'));
        self::assertSame(2, $this->game()->table('IWEB_S21_ItemOperation')->count());
    }

    /**
     * Seeds native account ownership, offline state and complete storage blobs.
     */
    private function seedAccount(string $accountId, int $identity, ?string $item = null): void
    {
        $this->game()->table('MEMB_INFO')->insert(['memb_guid' => $identity, 'memb___id' => $accountId, 'memb_name' => $accountId, 'bloc_code' => '0']);
        $this->game()->table('MEMB_STAT')->insert(['memb___id' => $accountId, 'ConnectStat' => 0]);
        $this->game()->table('Character')->insert([
            'AccountID' => $accountId, 'Name' => $accountId.'Hero', 'CharacterIndex' => $identity, 'Class' => 240,
            'Inventory' => str_repeat("\xFF", 5975), 'ExtInventory' => 0,
        ]);
        $warehouse = str_repeat("\xFF", 6000);
        $this->game()->table('warehouse')->insert([
            'AccountID' => $accountId, 'AccountIndex' => $identity,
            'Items' => $item === null ? $warehouse : substr_replace($warehouse, $item, 0, 25),
        ]);
        $this->game()->table('CashShopData')->insert(['AccountID' => $accountId, 'WCoinC' => $identity * 100, 'WCoinP' => 5, 'GoblinPoint' => 7]);
    }

    /**
     * Reads the complete native warehouse for byte-for-byte assertions.
     */
    private function warehouse(string $accountId): string
    {
        return $this->game()->table('warehouse')->where('AccountID', $accountId)->value('Items');
    }

    /**
     * Returns the disposable game projection.
     */
    private function game(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
