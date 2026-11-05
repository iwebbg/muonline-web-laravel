<?php

namespace Tests\Feature;

use App\Economy\WalletCurrency;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Game\Contracts\ItemSerialRepository;
use App\Game\Contracts\MarketItemEscrowRepository;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingBlockReason;
use App\Models\AccountReward;
use App\Models\AccountWallet;
use App\Models\ShopOffer;
use App\Models\ShopOfferItem;
use App\Models\ShopPurchase;
use App\Models\WalletTransaction;
use App\Models\WebStorageItem;
use App\Shop\AccountRewardStatus;
use App\Shop\Actions\PurchaseShopOffer;
use App\Shop\Exceptions\ShopPurchaseRejected;
use App\Shop\MysteryBoxRewardMode;
use App\Shop\RewardDeliveryDestination;
use App\Shop\ShopPurchaseBlockReason;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Verifies atomic Web Shop charging, ownership and delivery fallback behavior.
 */
final class ShopPurchaseTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms an identical replay returns the owned reward without charging twice.
     */
    public function test_item_purchase_is_idempotent_and_keeps_reward_when_writes_are_disabled(): void
    {
        $offer = $this->itemOffer();
        $this->wallet('buyer01', 500);
        $this->mockAccountExists();
        config()->set('game.writes_enabled', false);

        $action = app(PurchaseShopOffer::class);
        $firstReward = $action->execute('buyer01', $offer, 'purchase-request-01');
        $replayedReward = $action->execute('buyer01', $offer, 'purchase-request-01');

        $this->assertSame($firstReward->getKey(), $replayedReward->getKey());
        $this->assertDatabaseHas('account_wallets', [
            'account_id' => 'buyer01',
            'currency' => WalletCurrency::Credits->value,
            'balance' => 400,
        ]);
        $this->assertSame(1, ShopPurchase::query()->count());
        $this->assertSame(1, AccountReward::query()->count());
        $this->assertSame(1, WalletTransaction::query()->count());
        $this->assertSame(AccountRewardStatus::Ready, $firstReward->refresh()->status);
        $this->assertSame('writes_disabled', $firstReward->last_delivery_error);
    }

    /**
     * Confirms insufficient Credits roll back all commercial and reward records.
     */
    public function test_purchase_rejects_insufficient_credits_without_creating_ownership(): void
    {
        $offer = $this->itemOffer();
        $this->wallet('buyer02', 50);
        $this->mockAccountExists();

        try {
            app(PurchaseShopOffer::class)->execute('buyer02', $offer, 'purchase-request-02');
            $this->fail('The purchase should have been rejected.');
        } catch (ShopPurchaseRejected $exception) {
            $this->assertSame(ShopPurchaseBlockReason::InsufficientCredits, $exception->reason);
        }

        $this->assertDatabaseHas('account_wallets', [
            'account_id' => 'buyer02',
            'balance' => 50,
        ]);
        $this->assertDatabaseCount('shop_purchases', 0);
        $this->assertDatabaseCount('account_rewards', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    /**
     * Confirms a full game vault falls back to enabled Web Vault custody.
     */
    public function test_item_purchase_falls_back_to_web_vault_when_game_vault_is_full(): void
    {
        $offer = $this->itemOffer();
        $this->wallet('buyer03', 500);
        $this->mockAccountExists();
        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);
        $this->mock(ItemSerialRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('nextSerial')->once()->andReturn(1200);
        });
        $this->mock(MarketItemEscrowRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('deposit')
                ->once()
                ->andThrow(new MarketListingRejected(MarketListingBlockReason::StorageFull));
        });

        $reward = app(PurchaseShopOffer::class)->execute('buyer03', $offer, 'purchase-request-03');

        $this->assertSame(AccountRewardStatus::Claimed, $reward->status);
        $this->assertSame(RewardDeliveryDestination::WebVault, $reward->delivery_destination);
        $this->assertSame(1200, $reward->item_serial);
        $this->assertDatabaseHas('web_storage_items', [
            'account_id' => 'buyer03',
            'item_fingerprint' => $reward->item_fingerprint,
        ]);
        $this->assertSame(1, WebStorageItem::query()->count());
    }

    /**
     * Confirms the box release mode is immutable after the Credits purchase.
     */
    public function test_mystery_box_purchase_snapshots_the_all_items_reward_mode(): void
    {
        $offer = ShopOffer::factory()->allItems()->create(['price_credits' => 100]);
        ShopOfferItem::factory()->count(2)->for($offer, 'offer')->create();
        $this->wallet('buyer04', 500);
        $this->mockAccountExists();

        $reward = app(PurchaseShopOffer::class)->execute('buyer04', $offer, 'purchase-request-04');
        $offer->update(['mystery_box_reward_mode' => MysteryBoxRewardMode::OneRandom]);
        $purchase = ShopPurchase::query()->firstOrFail();

        $this->assertSame(MysteryBoxRewardMode::AllItems, $reward->mystery_box_reward_mode);
        $this->assertSame(MysteryBoxRewardMode::AllItems->value, $purchase->offer_snapshot['mystery_box_reward_mode']);
        $this->assertSame(MysteryBoxRewardMode::OneRandom, $offer->refresh()->mystery_box_reward_mode);
    }

    /**
     * Creates one published single-item shop offer backed by trusted item metadata.
     */
    private function itemOffer(): ShopOffer
    {
        $offer = ShopOffer::factory()->create([
            'title_en' => 'Kris',
            'title_bg' => 'Крис',
            'price_credits' => 100,
        ]);
        ShopOfferItem::factory()->for($offer, 'offer')->create();

        return $offer;
    }

    /**
     * Seeds the current Credits balance used by a purchase scenario.
     */
    private function wallet(string $accountId, int $balance): AccountWallet
    {
        return AccountWallet::query()->create([
            'account_id' => $accountId,
            'currency' => WalletCurrency::Credits,
            'balance' => $balance,
        ]);
    }

    /**
     * Replaces the legacy game lookup with an existing-account boundary.
     */
    private function mockAccountExists(): void
    {
        $this->mock(GameAccountLookupRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exists')->andReturnTrue();
        });
    }
}
