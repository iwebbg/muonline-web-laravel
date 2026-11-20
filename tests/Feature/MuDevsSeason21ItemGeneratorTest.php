<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Game\Contracts\ItemGeneratorCatalog;
use App\Game\Contracts\ItemSerialRepository;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\MuDevsSeason21\ItemCodecAdapterMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\ItemGeneratorCatalogMuDevsSeason21;
use App\Game\Versions\MuEmuSeason6\ItemGeneratorCatalogMuEmuSeason6;
use App\Models\AccountReward;
use App\Models\AccountWallet;
use App\Models\ShopPurchase;
use App\Models\WebStorageItem;
use App\Modules\ModuleManager;
use App\Providers\AppServiceProvider;
use App\Shop\AccountRewardStatus;
use App\Shop\RewardDeliveryDestination;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * Verifies S21 prices, native options, authenticated purchases and exact-item fallback.
 */
final class MuDevsSeason21ItemGeneratorTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Selects native adapters and installs only disposable S21 projections.
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
        $game = $this->app->make(DatabaseManager::class)->connection('game');
        $game->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));
        $game->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-web-columns.sql')));
    }

    /**
     * Preserves S6 matches and advances new class equipment through fixed price tiers.
     */
    #[TestWith([8, 29, 140])]
    #[TestWith([7, 45, 100])]
    #[TestWith([8, 45, 160])]
    #[TestWith([8, 98, 180])]
    #[TestWith([8, 138, 200])]
    #[TestWith([8, 150, 220])]
    #[TestWith([8, 106, 240])]
    #[TestWith([8, 410, 400])]
    #[TestWith([8, 116, 10])]
    #[TestWith([8, 388, 10])]
    #[TestWith([12, 40, 170])]
    public function test_prices_use_reviewed_s6_references(int $group, int $index, int $expectedPrice): void
    {
        $catalog = $this->app->make(ItemGeneratorCatalog::class);
        $this->assertInstanceOf(ItemGeneratorCatalogMuDevsSeason21::class, $catalog);
        $quote = $catalog->quote($this->nativeOptions($group, $index));
        $this->assertNotNull($quote);
        $this->assertSame($expectedPrice, $quote->priceCredits);
    }

    /**
     * Keeps every listed item constructible and every stored price traceable to an S6 anchor.
     */
    public function test_catalog_and_price_snapshot_remain_consistent(): void
    {
        $snapshot = json_decode(file_get_contents(resource_path('game/mudevs_season_21/item-generator-pricing.json')), true, flags: JSON_THROW_ON_ERROR);
        $legacy = json_decode(file_get_contents(resource_path('game/muemu_season_6/item-generator-pricing.json')), true, flags: JSON_THROW_ON_ERROR);
        $catalog = $this->app->make(ItemGeneratorCatalog::class);
        $items = $catalog->items();
        $this->assertCount(count($snapshot['items']), $items);
        $this->assertGreaterThan(2000, count($items));

        foreach ($items as $item) {
            $price = $snapshot['items'][$item['code']];
            $this->assertSame($legacy[$price['reference_code']]['base_price'] + 20 * $price['tier_steps'], $item['basePrice'], $item['code']);
            [$group, $index] = array_map('intval', explode(':', $item['code']));
            $this->assertNotNull($catalog->quote($this->nativeOptions($group, $index)), $item['code']);
        }
    }

    /**
     * Reuses S6 surcharges while exposing S21 compatibility and native ancient prices.
     */
    public function test_option_prices_match_s6_without_reusing_legacy_option_rules(): void
    {
        $catalog = $this->app->make(ItemGeneratorCatalog::class);
        $this->assertSame(
            $this->app->make(ItemGeneratorCatalogMuEmuSeason6::class)->priceModifiers(),
            $catalog->priceModifiers(),
        );
        $items = array_column($catalog->items(), null, 'code');
        $this->assertFalse($items['0:0']['allowsSkill']);
        $this->assertSame(13, $items['0:32']['ancientPrices'][6]);
        $this->assertSame(20, $items['0:32']['ancientPrices'][10]);
        $this->assertSame(3, $items['0:26']['maxSockets']);
        $this->assertFalse($items['8:29']['allows380']);
    }

    /**
     * Rejects unsupported wings, commodities and impossible native option combinations.
     */
    #[TestWith([12, 15, []])]
    #[TestWith([12, 262, []])]
    #[TestWith([0, 0, ['has_skill' => true]])]
    #[TestWith([0, 26, ['socket_count' => 4]])]
    #[TestWith([0, 26, ['harmony_option' => 16, 'socket_count' => 1]])]
    #[TestWith([0, 32, ['ancient_option' => 6, 'excellent_mask' => 1]])]
    #[TestWith([8, 29, ['has_380_option' => true]])]
    #[TestWith([8, 29, ['level' => 16]])]
    public function test_invalid_construction_is_not_quoted(int $group, int $index, array $overrides): void
    {
        $this->assertNull($this->app->make(ItemGeneratorCatalog::class)->quote($this->nativeOptions($group, $index, $overrides)));
    }

    /**
     * Charges native set variants by their +5 or +10 strength rather than the raw variant code.
     */
    #[TestWith([5, 13])]
    #[TestWith([6, 13])]
    #[TestWith([9, 20])]
    #[TestWith([10, 20])]
    public function test_ancient_set_variants_share_the_strength_tier_price(int $ancientOption, int $surcharge): void
    {
        $quote = $this->app->make(ItemGeneratorCatalog::class)->quote($this->nativeOptions(0, 32, ['ancient_option' => $ancientOption]));
        $this->assertNotNull($quote);
        $this->assertSame($surcharge, $quote->priceBreakdown['ancient']);
        $this->assertSame(52 + $surcharge, $quote->priceCredits);
    }

    /**
     * Does not charge a wing additional-option selector as an extra excellent option.
     */
    public function test_wing_additional_variant_does_not_inflate_excellent_price(): void
    {
        $quote = $this->app->make(ItemGeneratorCatalog::class)->quote($this->nativeOptions(12, 40, ['option_level' => 7, 'excellent_mask' => 47]));
        $this->assertNotNull($quote);
        $this->assertSame(60, $quote->priceBreakdown['excellent_options']);
        $this->assertSame(265, $quote->priceCredits);
    }

    /**
     * Exposes the native generator only after authentication with the shared Browse interface.
     */
    public function test_generator_page_is_available_to_s21_accounts(): void
    {
        $this->get('/account/shop/item-generator')->assertRedirect('/login');
        $this->actingAs($this->account())->get('/account/shop/item-generator')
            ->assertOk()->assertSee('Item Generator')->assertSee('Imperial Knight Armor')->assertSee('Browse');
    }

    /**
     * Ignores submitted prices and delivers exact native bytes once to game or web custody.
     */
    #[TestWith(['0:26', 3, 0, 322, false])]
    #[TestWith(['0:32', 0, 6, 175, false])]
    #[TestWith(['0:26', 3, 0, 322, true])]
    #[TestWith(['0:32', 0, 6, 175, true])]
    public function test_purchase_uses_server_price_and_exact_native_delivery(string $code, int $sockets, int $ancient, int $price, bool $hasWarehouse): void
    {
        $this->actingAs($this->account());
        $game = $this->app->make(DatabaseManager::class)->connection('game');
        $game->table('MEMB_INFO')->insert(['memb_guid' => 2, 'memb___id' => 'buyer', 'memb_name' => 'buyer', 'bloc_code' => '0']);
        if ($hasWarehouse) {
            $game->table('MEMB_STAT')->insert(['memb___id' => 'buyer', 'ConnectStat' => 0]);
            $game->table('warehouse')->insert(['AccountID' => 'buyer', 'AccountIndex' => 2, 'Items' => str_repeat("\xFF", 6000)]);
        }

        AccountWallet::factory()->create(['account_id' => 'buyer', 'balance' => 1000]);
        $this->mock(ItemSerialRepository::class)->shouldReceive('nextSerial')->once()->andReturn(123);
        $token = 's21-generator-csrf';
        $payload = [
            '_token' => $token, 'item_code' => $code, 'level' => 15, 'durability' => 255,
            'option_level' => 7, 'has_skill' => false, 'has_luck' => true, 'excellent_mask' => 0,
            'ancient_option' => $ancient, 'harmony_option' => 0, 'socket_count' => $sockets,
            'has_380_option' => false, 'idempotency_key' => (string) Str::uuid(), 'price_credits' => 1,
        ];
        $this->withSession(['_token' => $token])->post('/account/shop/item-generator', $payload)
            ->assertRedirect(route('account.rewards.index'))->assertSessionHasNoErrors();
        $this->withSession(['_token' => $token])->post('/account/shop/item-generator', $payload)
            ->assertRedirect(route('account.rewards.index'))->assertSessionHasNoErrors();

        $reward = AccountReward::query()->sole();
        $this->assertSame(AccountRewardStatus::Claimed, $reward->status);
        if ($hasWarehouse) {
            $this->assertSame(RewardDeliveryDestination::GameVault, $reward->delivery_destination);
            $this->assertSame($reward->item_binary, substr($game->table('warehouse')->value('Items'), 0, 25));
            $this->assertDatabaseCount('web_storage_items', 0);
        } else {
            $this->assertSame(RewardDeliveryDestination::WebVault, $reward->delivery_destination);
            $this->assertSame($reward->item_binary, WebStorageItem::query()->sole()->item_binary);
        }
        $this->assertSame(1000 - $price, AccountWallet::query()->where('account_id', 'buyer')->value('balance'));
        $this->assertSame($price, ShopPurchase::query()->sole()->price_credits);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame($hasWarehouse ? 1 : 0, $game->table('IWEB_S21_ItemOperation')->count());
        $record = $this->app->make(ItemCodecAdapterMuDevsSeason21::class)->decode($reward->item_binary, 1)[0];
        $this->assertSame($code, $record->code());
        $this->assertSame($ancient, $record->ancientOption);
        $this->assertSame($sockets, count(array_filter($record->socketOptions, static fn (int $value): bool => $value === 254)));
    }

    /**
     * Returns one authenticated web identity without creating game session or warehouse rows.
     */
    private function account(): GameAccount
    {
        return new GameAccount(2, 'buyer', 'Buyer', null, false, false, '');
    }

    /**
     * Builds a native item configuration with explicit ordinary defaults.
     *
     * @param  array<string, int|bool>  $overrides
     * @return array<string, int|bool>
     */
    private function nativeOptions(int $group, int $index, array $overrides = []): array
    {
        return array_replace([
            'group' => $group, 'index' => $index, 'level' => 0, 'durability' => 255,
            'option_level' => 0, 'has_skill' => false, 'has_luck' => false, 'excellent_mask' => 0,
            'ancient_option' => 0, 'harmony_option' => 0, 'socket_count' => 0, 'has_380_option' => false,
        ], $overrides);
    }
}
