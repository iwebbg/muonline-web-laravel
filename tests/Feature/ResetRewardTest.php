<?php

namespace Tests\Feature;

use App\Game\Contracts\GameAccountLookupRepository;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\AccountReward;
use App\Models\AccountWallet;
use App\Models\CharacterGrandResetOperation;
use App\Models\CharacterResetOperation;
use App\Models\ShopOffer;
use App\Models\ShopOfferItem;
use App\Models\WalletTransaction;
use App\Reset\Actions\GrantResetReward;
use App\Reset\ResetProfileSettings;
use App\Reset\ResetRewardType;
use App\Shop\AccountRewardStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Verifies reset rewards are durable and cannot be duplicated by request replay.
 */
final class ResetRewardTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms a completed reset grants its configured Credits exactly once.
     */
    public function test_completed_reset_grants_configured_credits_once(): void
    {
        $this->mock(GameAccountLookupRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exists')->once()->andReturnTrue();
        });
        $defaults = ResetProfileSettings::defaults();
        $settingsSnapshot = [
            ...$defaults->toPersistenceArray(),
            'reset_reward_type' => ResetRewardType::Credits->value,
            'reset_reward_credits' => 75,
        ];
        $operation = CharacterResetOperation::factory()->create([
            'account_id' => 'reward01',
            'status' => CharacterOperationStatus::Completed,
            'settings_snapshot' => $settingsSnapshot,
        ]);

        $action = app(GrantResetReward::class);
        $action->forReset($operation);
        $action->forReset($operation->refresh());

        $this->assertSame(75, AccountWallet::query()->sole()->balance);
        $this->assertSame(1, WalletTransaction::query()->count());
        $this->assertNotNull($operation->refresh()->reward_wallet_transaction_id);
    }

    /**
     * Confirms a completed Grand Reset creates one sealed Mystery Box ownership record.
     */
    public function test_completed_grand_reset_grants_configured_mystery_box_once(): void
    {
        $offer = ShopOffer::factory()->mysteryBox()->create();
        ShopOfferItem::factory()->create(['shop_offer_id' => $offer->getKey()]);
        $defaults = ResetProfileSettings::defaults();
        $settingsSnapshot = [
            ...$defaults->toPersistenceArray(),
            'grand_reset_reward_type' => ResetRewardType::MysteryBox->value,
            'grand_reset_reward_shop_offer_id' => $offer->getKey(),
        ];
        $operation = CharacterGrandResetOperation::factory()->create([
            'account_id' => 'reward02',
            'status' => CharacterOperationStatus::Completed,
            'settings_snapshot' => $settingsSnapshot,
        ]);

        $action = app(GrantResetReward::class);
        $action->forGrandReset($operation);
        $action->forGrandReset($operation->refresh());

        $reward = AccountReward::query()->sole();
        $this->assertSame(AccountRewardStatus::Sealed, $reward->status);
        $this->assertSame($offer->getKey(), $reward->shop_offer_id);
        $this->assertNotNull($operation->refresh()->account_reward_id);
    }
}
