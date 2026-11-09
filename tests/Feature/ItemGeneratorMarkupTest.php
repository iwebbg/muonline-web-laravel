<?php

namespace Tests\Feature;

use App\Economy\WalletCurrency;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Models\AccountWallet;
use App\Models\EconomySetting;
use App\Models\ShopPurchase;
use App\Shop\Actions\PurchaseGeneratedItem;
use App\Shop\EconomySettings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Verifies administrator markup is applied to the complete server-calculated item price.
 */
final class ItemGeneratorMarkupTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms a percentage markup is charged once and persisted in the purchase snapshot.
     */
    public function test_purchase_adds_configured_percentage_to_complete_generated_item_price(): void
    {
        EconomySetting::factory()->create([
            ...EconomySettings::defaults()->toPersistenceArray(),
            'item_generator_markup_percentage' => 25,
        ]);
        AccountWallet::query()->create([
            'account_id' => 'builder01',
            'currency' => WalletCurrency::Credits,
            'balance' => 500,
        ]);
        $this->mock(GameAccountLookupRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exists')->once()->andReturnTrue();
        });
        config()->set('game.writes_enabled', false);

        app(PurchaseGeneratedItem::class)->execute(
            accountId: 'builder01',
            options: $this->itemOptions(),
            idempotencyKey: 'generator-markup-request',
        );

        $purchase = ShopPurchase::query()->sole();
        $this->assertSame(249, $purchase->price_credits);
        $this->assertSame(50, $purchase->offer_snapshot['price_breakdown']['markup']);
        $this->assertDatabaseHas('account_wallets', [
            'account_id' => 'builder01',
            'currency' => WalletCurrency::Credits->value,
            'balance' => 251,
        ]);
    }

    /**
     * Returns one known 199-Credits item configuration before markup.
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
}
