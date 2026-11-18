<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Economy\WalletCurrency;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Game\Contracts\ItemGeneratorCatalog;
use App\Game\Contracts\ItemGrantRules;
use App\Game\Contracts\ItemSerialRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\MuEmuSeason6\ItemGeneratorCatalogMuEmuSeason6;
use App\Game\Versions\MuEmuSeason6\ItemGrantRulesMuEmuSeason6;
use App\Game\Versions\MuEmuSeason6\MuEmuSeason6Profile;
use App\Game\Versions\Version97d99i\ItemGeneratorCatalog97d99i;
use App\Models\AccountReward;
use App\Models\AccountWallet;
use App\Models\ShopPurchase;
use App\Models\WalletTransaction;
use App\Models\WebStorageItem;
use App\Shop\AccountRewardStatus;
use App\Shop\Actions\PurchaseGeneratedItem;
use App\Shop\RewardDeliveryDestination;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Verifies version-owned custom item pricing, validation and Credits charging.
 */
final class ItemGeneratorTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms the supplied webshop formula is applied to every selected option.
     */
    public function test_quote_uses_version_item_price_and_option_components(): void
    {
        $quote = app(ItemGeneratorCatalog97d99i::class)->quote($this->itemOptions());

        $this->assertNotNull($quote);
        $this->assertSame(199, $quote->priceCredits);
        $this->assertSame([
            'base' => 2,
            'level' => 44,
            'additional_option' => 35,
            'skill' => 13,
            'luck' => 15,
            'excellent_options' => 90,
        ], $quote->priceBreakdown);
    }

    /**
     * Confirms unsupported per-item options are rejected before charging.
     */
    public function test_quote_rejects_an_option_disabled_for_the_selected_item(): void
    {
        $options = $this->itemOptions();
        $options['group'] = 0;
        $options['index'] = 0;

        $this->assertNull(app(ItemGeneratorCatalog97d99i::class)->quote($options));
    }

    /**
     * Confirms one generated purchase owns the exact item and never charges twice.
     */
    public function test_generated_item_purchase_is_atomic_and_idempotent(): void
    {
        AccountWallet::query()->create([
            'account_id' => 'builder01',
            'currency' => WalletCurrency::Credits,
            'balance' => 500,
        ]);
        $this->mock(GameAccountLookupRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exists')->andReturnTrue();
        });
        config()->set('game.writes_enabled', false);

        $action = app(PurchaseGeneratedItem::class);
        $firstReward = $action->execute('builder01', $this->itemOptions(), 'generator-request-01');
        $replayedReward = $action->execute('builder01', $this->itemOptions(), 'generator-request-01');

        $this->assertSame($firstReward->getKey(), $replayedReward->getKey());
        $this->assertSame(AccountRewardStatus::Ready, $firstReward->refresh()->status);
        $this->assertSame('4:0', $firstReward->selected_item_snapshot['item_code']);
        $this->assertDatabaseHas('account_wallets', [
            'account_id' => 'builder01',
            'currency' => WalletCurrency::Credits->value,
            'balance' => 301,
        ]);
        $this->assertSame(1, ShopPurchase::query()->count());
        $this->assertSame(1, AccountReward::query()->count());
        $this->assertSame(1, WalletTransaction::query()->count());
    }

    /**
     * Delivers generated purchases to Web Vault before any game session or warehouse exists.
     */
    public function test_generated_purchase_for_new_account_falls_back_to_web_vault_once(): void
    {
        AccountWallet::factory()->create(['account_id' => 'builder01', 'balance' => 500]);
        $this->mock(GameAccountLookupRepository::class)->shouldReceive('exists')->andReturnTrue();
        $this->mock(ItemSerialRepository::class)->shouldReceive('nextSerial')->once()->andReturn(123);
        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);
        $game = $this->app->make(DatabaseManager::class)->connection('game');
        $game->statement('CREATE TABLE MEMB_STAT (memb___id TEXT PRIMARY KEY, ConnectStat INTEGER NULL)');
        $game->statement('CREATE TABLE warehouse (AccountID TEXT PRIMARY KEY, Items BLOB NULL)');
        $action = $this->app->make(PurchaseGeneratedItem::class);

        $reward = $action->execute('builder01', $this->itemOptions(), 'new-account-generator');
        $action->execute('builder01', $this->itemOptions(), 'new-account-generator');

        $this->assertSame(AccountRewardStatus::Claimed, $reward->refresh()->status);
        $this->assertSame(RewardDeliveryDestination::WebVault, $reward->delivery_destination);
        $storedItem = WebStorageItem::query()->sole();
        $this->assertSame('builder01', $storedItem->account_id);
        $this->assertSame($reward->item_binary, $storedItem->item_binary);
        $this->assertSame(WebStorageItemStatus::Available, $storedItem->status);
        $this->assertSame(301, AccountWallet::query()->where('account_id', 'builder01')->value('balance'));
        $this->assertSame(1, WalletTransaction::query()->count());
        $this->assertSame(0, $game->table('warehouse')->count());
        $this->assertSame(0, $game->table('MEMB_STAT')->count());
    }

    /**
     * Confirms the generator is an authenticated account tool.
     */
    public function test_generator_page_requires_an_account_and_lists_priced_items(): void
    {
        $this->get(route('account.item-generator.index'))->assertRedirect(route('login'));

        $response = $this->actingAs($this->account())->get(route('account.item-generator.index'));

        $response->assertOk();
        $response->assertSee('Item Generator');
        $response->assertSee('Kris');
    }

    /**
     * Confirms the Season 6 page exposes priced 380 and socket items with advanced controls.
     */
    public function test_season_six_generator_lists_advanced_items_and_controls(): void
    {
        $this->bindSeasonSixItemServices();

        $response = $this->actingAs($this->account())->get(route('account.item-generator.index'));

        $response->assertOk();
        $response->assertSee('Bone Blade');
        $response->assertSee('Deadly Staff');
        $response->assertSee('Season 6 options');
        $response->assertSee('380 option');
        $response->assertSee('Socket slots');
    }

    /**
     * Confirms a valid Season 6 option is priced, persisted and charged through the endpoint.
     */
    public function test_season_six_generator_purchases_a_380_item(): void
    {
        $this->bindSeasonSixItemServices();
        AccountWallet::query()->create([
            'account_id' => 'builder01',
            'currency' => WalletCurrency::Credits,
            'balance' => 500,
        ]);
        $this->mock(GameAccountLookupRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exists')->andReturnTrue();
        });
        config()->set('game.writes_enabled', false);

        $response = $this->actingAs($this->account())->post(route('account.item-generator.purchase'), [
            'item_code' => '0:22',
            'level' => 0,
            'durability' => 255,
            'option_level' => 0,
            'has_skill' => false,
            'has_luck' => false,
            'excellent_mask' => 0,
            'ancient_option' => 0,
            'harmony_option' => 0,
            'socket_count' => 0,
            'has_380_option' => true,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertRedirect(route('account.rewards.index'));
        $this->assertSame(true, AccountReward::query()->sole()->selected_item_snapshot['has_380_option']);
        $this->assertDatabaseHas('account_wallets', [
            'account_id' => 'builder01',
            'currency' => WalletCurrency::Credits->value,
            'balance' => 338,
        ]);
    }

    /**
     * Returns a fully-priced 97d+99i bow configuration.
     *
     * @return array{group: int, index: int, level: int, durability: int, option_level: int, has_skill: bool, has_luck: bool, excellent_mask: int, ancient_option: int}
     */
    private function itemOptions(): array
    {
        return [
            'group' => 4,
            'index' => 0,
            'level' => 11,
            'durability' => 255,
            'option_level' => 7,
            'has_skill' => true,
            'has_luck' => true,
            'excellent_mask' => 63,
            'ancient_option' => 0,
        ];
    }

    /**
     * Creates one authenticated legacy account for endpoint coverage.
     */
    private function account(): GameAccount
    {
        return new GameAccount(1, 'builder01', 'Builder', null, false, false, 'secret1');
    }

    /**
     * Replaces the default test profile with the isolated Season 6 item services.
     */
    private function bindSeasonSixItemServices(): void
    {
        $this->app->instance(GameVersionProfile::class, new MuEmuSeason6Profile);
        $this->app->instance(ItemGeneratorCatalog::class, app(ItemGeneratorCatalogMuEmuSeason6::class));
        $this->app->instance(ItemGrantRules::class, new ItemGrantRulesMuEmuSeason6);
    }
}
