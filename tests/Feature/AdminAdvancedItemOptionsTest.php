<?php

namespace Tests\Feature;

use App\Game\Contracts\GameAccountLookupRepository;
use App\Game\Contracts\ItemCodec;
use App\Game\Contracts\ItemEscrowRepository;
use App\Game\Contracts\ItemSerialRepository;
use App\Game\Contracts\MarketItemEscrowRepository;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Repositories\FixtureRealmReadRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Market\MarketEscrowResult;
use App\Models\AccountWallet;
use App\Models\Administrator;
use App\Models\ItemGrantOperation;
use App\Models\ShopOffer;
use App\Models\ShopOfferItem;
use App\Modules\ModuleManager;
use App\Providers\AppServiceProvider;
use App\Shop\AccountRewardStatus;
use App\Shop\Actions\OpenAccountReward;
use App\Shop\Actions\PurchaseShopOffer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Exercises version-owned advanced choices through administrator forms and real item encoders.
 */
final class AdminAdvancedItemOptionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Keeps advanced fields in the audit and native bytes without reserving duplicate serials.
     *
     * @param  array<string, int|bool>  $advanced
     */
    #[DataProvider('validItems')]
    public function test_grants_deliver_supported_advanced_options(string $profile, string $code, array $advanced): void
    {
        $this->configureProfile($profile);
        $this->mock(ItemSerialRepository::class)->shouldReceive('nextSerial')->once()->andReturn(61);
        $administrator = Administrator::factory()->create();
        $payload = $this->grantPayload($code, $advanced);

        $this->actingAs($administrator, 'admin')->withSession(['_token' => 'advanced-options'])
            ->post(route('admin.item-grants.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('admin.item-grants.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();

        $operation = ItemGrantOperation::query()->sole();
        $this->assertEncodedOptions($operation->item_binary, $code, $advanced);
        $this->assertSame(61, $operation->serial);

        foreach ($advanced as $field => $value) {
            $this->assertSame($value, $operation->context_snapshot['options'][$field]);
        }
    }

    /**
     * Retains persisted options through offer editing, purchases and Mystery Box opening.
     *
     * @param  array<string, int|bool>  $advanced
     */
    #[DataProvider('validItems')]
    public function test_shop_offers_persist_and_deliver_advanced_options(string $profile, string $code, array $advanced): void
    {
        $this->configureProfile($profile);
        $this->mock(ItemSerialRepository::class)->shouldReceive('nextSerial')->once()->andReturn(61);
        $administrator = Administrator::factory()->create();
        $item = $this->grantPayload($code, $advanced);
        unset($item['_token'], $item['account_id'], $item['reason'], $item['idempotency_key']);
        $payload = [
            '_token' => 'advanced-options', 'type' => 'mystery_box', 'mystery_box_reward_mode' => 'one_random',
            'class_scope' => 'all', 'title_en' => 'Advanced reward', 'title_bg' => 'Advanced reward',
            'price_credits' => 100, 'status' => 'published', 'sort_order' => 0,
            'items' => [[...$item, 'weight' => 1]],
        ];
        $this->actingAs($administrator, 'admin')->withSession(['_token' => 'advanced-options'])
            ->post(route('admin.shop-offers.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();

        $offer = ShopOffer::query()->sole();
        $storedItem = ShopOfferItem::query()->sole();

        foreach ($advanced as $field => $value) {
            $this->assertSame($value, $storedItem->getAttribute($field));
            $this->assertSame($value, $storedItem->snapshot()[$field]);
        }

        $this->get(route('admin.shop-offers.edit', $offer))->assertOk();
        AccountWallet::factory()->create(['account_id' => 'owner', 'balance' => 500]);
        $purchase = $this->app->make(PurchaseShopOffer::class);
        $key = (string) Str::uuid();
        $reward = $purchase->execute('owner', $offer, $key);
        $reward = $this->app->make(OpenAccountReward::class)->execute('owner', $reward);
        $purchase->execute('owner', $offer, $key);

        $this->assertSame(AccountRewardStatus::Claimed, $reward->refresh()->status, $reward->last_delivery_error ?? '');
        $this->assertEncodedOptions($reward->item_binary, $code, $advanced);
        $this->assertSame(400, AccountWallet::query()->value('balance'));
    }

    /**
     * Rejects forged combinations before reserving serials or saving shop rewards.
     *
     * @param  array<string, int|bool>  $advanced
     */
    #[DataProvider('invalidItems')]
    public function test_incompatible_advanced_options_are_rejected(string $profile, string $code, array $advanced): void
    {
        $this->configureProfile($profile);
        $this->mock(ItemSerialRepository::class)->shouldNotReceive('nextSerial');
        $this->actingAs(Administrator::factory()->create(), 'admin')->withSession(['_token' => 'advanced-options'])
            ->post(route('admin.item-grants.store'), $this->grantPayload($code, $advanced))
            ->assertRedirect()->assertSessionHasErrors();
        $this->assertDatabaseCount('item_grant_operations', 0);
        $item = $this->grantPayload($code, $advanced);
        unset($item['_token'], $item['account_id'], $item['reason'], $item['idempotency_key']);
        $this->post(route('admin.shop-offers.store'), [
            '_token' => 'advanced-options', 'type' => 'item', 'mystery_box_reward_mode' => 'one_random',
            'class_scope' => 'all', 'title_en' => 'Invalid reward', 'title_bg' => 'Invalid reward',
            'price_credits' => 100, 'status' => 'published', 'sort_order' => 0,
            'items' => [[...$item, 'weight' => 1]],
        ])->assertRedirect()->assertSessionHasErrors();
        $this->assertDatabaseCount('shop_offers', 0);

    }

    /**
     * Supplies valid native combinations and legacy adapters with no advanced fields enabled.
     *
     * @return array<string, array{string, string, array<string, int|bool>}>
     */
    public static function validItems(): array
    {
        return [
            '97d unchanged' => ['darksteam_97d99i', '0:0', []],
            '1.0M unchanged' => ['darksteam_1_0m', '0:0', []],
            'S6 ancient' => ['muemu_season_6', '8:5', ['ancient_option' => 5]],
            'S6 harmony' => ['muemu_season_6', '0:0', ['harmony_option' => 17]],
            'S6 sockets' => ['muemu_season_6', '0:26', ['socket_count' => 3]],
            'S6 380' => ['muemu_season_6', '8:29', ['has_380_option' => true]],
            'S21 ancient +5' => ['mudevs_season_21', '8:5', ['ancient_option' => 5]],
            'S21 ancient +10' => ['mudevs_season_21', '8:5', ['ancient_option' => 9]],
            'S21 harmony' => ['mudevs_season_21', '0:0', ['harmony_option' => 17]],
            'S21 sockets' => ['mudevs_season_21', '0:26', ['socket_count' => 3]],
        ];
    }

    /**
     * Supplies unsupported items, incompatible shared-byte combinations and legacy-only exclusions.
     *
     * @return array<string, array{string, string, array<string, int|bool>}>
     */
    public static function invalidItems(): array
    {
        return [
            '97d harmony' => ['darksteam_97d99i', '0:0', ['harmony_option' => 17]],
            '1.0M ancient' => ['darksteam_1_0m', '8:5', ['ancient_option' => 5]],
            'S6 wrong ancient item' => ['muemu_season_6', '0:0', ['ancient_option' => 5]],
            'S6 wrong socket item' => ['muemu_season_6', '0:0', ['socket_count' => 1]],
            'S6 wrong 380 item' => ['muemu_season_6', '0:0', ['has_380_option' => true]],
            'S21 wrong ancient item' => ['mudevs_season_21', '0:0', ['ancient_option' => 5]],
            'S21 ancient with excellent' => ['mudevs_season_21', '8:5', ['ancient_option' => 5, 'excellent_mask' => 1]],
            'S21 wrong socket item' => ['mudevs_season_21', '0:0', ['socket_count' => 1]],
            'S21 harmony and sockets' => ['mudevs_season_21', '0:26', ['harmony_option' => 17, 'socket_count' => 3]],
            'S21 380 unverified' => ['mudevs_season_21', '8:29', ['has_380_option' => true]],
            'S21 unknown Harmony' => ['mudevs_season_21', '0:0', ['harmony_option' => 241]],
            'S21 Harmony above item level' => ['mudevs_season_21', '0:0', ['harmony_option' => 31]],
        ];
    }

    /**
     * Selects actual version bindings and uses only portable game projections or mocked transports.
     */
    private function configureProfile(string $profile): void
    {
        config()->set('game.profile.key', $profile);
        config()->set('game.data_source', 'database');
        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);
        $this->app->forgetInstance(GameVersionProfile::class);
        $this->app->forgetInstance(ModuleManager::class);
        $this->app->forgetInstance(RealmReadRepository::class);
        (new AppServiceProvider($this->app))->register();
        $this->app->bind(RealmReadRepository::class, FixtureRealmReadRepository::class);

        if ($profile === 'mudevs_season_21') {
            $game = $this->app->make(DatabaseManager::class)->connection('game');
            $game->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));
            $game->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-web-columns.sql')));
            $game->table('MEMB_INFO')->insert(['memb_guid' => 1, 'memb___id' => 'owner']);
            $game->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 0]);
            $game->table('warehouse')->insert(['AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => str_repeat("\xFF", 6000)]);
        } else {
            $this->mock(GameAccountLookupRepository::class)->shouldReceive('exists')->andReturnTrue();
            $this->mock(ItemEscrowRepository::class)->shouldReceive('deposit')->andReturn(MarketEscrowResult::Deposited);
            $this->mock(MarketItemEscrowRepository::class)->shouldReceive('deposit')->andReturn(MarketEscrowResult::Deposited);
        }
    }

    /**
     * Supplies basic options at a level accepted by all four adapters.
     *
     * @param  array<string, int|bool>  $advanced
     * @return array<string, int|bool|string>
     */
    private function grantPayload(string $code, array $advanced): array
    {
        return [
            '_token' => 'advanced-options', 'account_id' => 'owner', 'item_code' => $code,
            'level' => 9, 'durability' => 255, 'option_level' => 0,
            'has_skill' => false, 'has_luck' => false, 'excellent_mask' => 0,
            'ancient_option' => 0, 'harmony_option' => 0, 'socket_count' => 0, 'has_380_option' => false,
            'reason' => 'Advanced item option verification', 'idempotency_key' => (string) Str::uuid(),
            ...$advanced,
        ];
    }

    /**
     * Reads delivered bytes using the actual adapter and compares the requested fields.
     *
     * @param  array<string, int|bool>  $advanced
     */
    private function assertEncodedOptions(string $binary, string $code, array $advanced): void
    {
        $item = $this->app->make(ItemCodec::class)->decode($binary, 1)[0];
        $this->assertSame($code, $item->code());
        $this->assertSame((int) ($advanced['ancient_option'] ?? 0), $item->ancientOption);
        $this->assertSame((int) ($advanced['harmony_option'] ?? 0), $item->harmonyOption);
        if (config('game.profile.key') === 'mudevs_season_21' && ($advanced['harmony_option'] ?? 0) === 17) {
            $this->assertContains('Add Min Physi Damage +3', array_column($item->nativePresentation['details'], 'value'));
        }

        $this->assertSame((bool) ($advanced['has_380_option'] ?? false), $item->has380Option);
        $count = (int) ($advanced['socket_count'] ?? 0);
        $this->assertSame($count, count(array_filter($item->socketOptions, static fn (int $value): bool => $value === 254)));
    }
}
