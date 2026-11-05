<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Economy\WalletCurrency;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Game\Versions\Version97d99i\ItemGeneratorCatalog97d99i;
use App\Models\AccountReward;
use App\Models\AccountWallet;
use App\Models\ShopPurchase;
use App\Models\WalletTransaction;
use App\Shop\AccountRewardStatus;
use App\Shop\Actions\PurchaseGeneratedItem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
}
