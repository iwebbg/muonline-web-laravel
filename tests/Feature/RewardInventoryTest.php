<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Models\AccountReward;
use App\Shop\AccountRewardStatus;
use App\Shop\Actions\OpenAccountReward;
use App\Shop\Exceptions\RewardOpenRejected;
use App\Shop\MysteryBoxRewardMode;
use App\Shop\RewardOpenBlockReason;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies sealed reward ownership and one-time Mystery Box selection.
 */
final class RewardInventoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms revealed delivery items expose the shared item option tooltip.
     */
    public function test_reward_delivery_history_renders_the_shared_item_tooltip(): void
    {
        AccountReward::factory()->create([
            'account_id' => 'owner04',
            'status' => AccountRewardStatus::Claimed,
            'selected_item_snapshot' => [
                ...$this->rewardItem('0:0', 'Kris'),
                'level' => 11,
                'has_luck' => true,
                'excellent_mask' => 1,
            ],
        ]);

        $response = $this
            ->actingAs($this->account('owner04'))
            ->get(route('account.rewards.index'));

        $response
            ->assertOk()
            ->assertSee('shop-item-preview', false)
            ->assertSee('itemInspector(', false)
            ->assertSee('item-tooltip', false);
    }

    /**
     * Confirms opening a box persists one result and never rerolls it on replay.
     */
    public function test_mystery_box_opens_once_and_keeps_the_selected_item(): void
    {
        config()->set('game.writes_enabled', false);
        $reward = AccountReward::factory()->create(['account_id' => 'owner01']);
        $action = app(OpenAccountReward::class);

        $opened = $action->execute('owner01', $reward);
        $replayed = $action->execute('owner01', $reward);

        $this->assertSame(AccountRewardStatus::Ready, $opened->status);
        $this->assertSame('Kris', $opened->selected_item_snapshot['item_name']);
        $this->assertSame($opened->selected_item_snapshot, $replayed->selected_item_snapshot);
        $this->assertTrue($opened->opened_at->equalTo($replayed->opened_at));
        $this->assertSame('writes_disabled', $replayed->last_delivery_error);
    }

    /**
     * Confirms one account cannot reveal a reward owned by another account.
     */
    public function test_mystery_box_rejects_non_owner(): void
    {
        $reward = AccountReward::factory()->create(['account_id' => 'owner02']);

        try {
            app(OpenAccountReward::class)->execute('intruder', $reward);
            $this->fail('The non-owner should not be able to open the reward.');
        } catch (RewardOpenRejected $exception) {
            $this->assertSame(RewardOpenBlockReason::NotOwned, $exception->reason);
        }

        $this->assertSame(AccountRewardStatus::Sealed, $reward->refresh()->status);
        $this->assertNull($reward->selected_item_snapshot);
    }

    /**
     * Confirms an all-items box releases each immutable pool entry exactly once.
     */
    public function test_all_items_mystery_box_releases_every_item_without_duplicates(): void
    {
        config()->set('game.writes_enabled', false);
        $reward = AccountReward::factory()->create([
            'account_id' => 'owner03',
            'mystery_box_reward_mode' => MysteryBoxRewardMode::AllItems,
            'reward_pool_snapshot' => [
                $this->rewardItem('0:0', 'Kris'),
                $this->rewardItem('0:1', 'Short Sword'),
            ],
        ]);
        $action = app(OpenAccountReward::class);

        $opened = $action->execute('owner03', $reward);
        $action->execute('owner03', $reward);
        $releasedRewards = AccountReward::query()
            ->where('account_id', 'owner03')
            ->where('reward_type', 'item')
            ->orderBy('id')
            ->get();

        $this->assertSame(AccountRewardStatus::Claimed, $opened->status);
        $this->assertSame('bundle_delivery_pending', $opened->last_delivery_error);
        $this->assertCount(2, $releasedRewards);
        $this->assertSame('Kris', $releasedRewards[0]->selected_item_snapshot['item_name']);
        $this->assertSame('Short Sword', $releasedRewards[1]->selected_item_snapshot['item_name']);
        $this->assertSame(AccountRewardStatus::Ready, $releasedRewards[0]->status);
        $this->assertSame(AccountRewardStatus::Ready, $releasedRewards[1]->status);
        $this->assertSame(3, AccountReward::query()->where('account_id', 'owner03')->count());
    }

    /**
     * Returns one exact item snapshot used by a sealed reward pool.
     *
     * @return array<string, int|string|bool>
     */
    private function rewardItem(string $itemCode, string $itemName): array
    {
        return [
            'item_code' => $itemCode,
            'item_name' => $itemName,
            'item_width' => 1,
            'item_height' => 2,
            'level' => 0,
            'durability' => 255,
            'option_level' => 0,
            'has_skill' => false,
            'has_luck' => false,
            'excellent_mask' => 0,
            'ancient_option' => 0,
            'weight' => 1,
        ];
    }

    /**
     * Creates one authenticated fixture-backed game account.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: $username,
            displayName: $username,
            email: null,
            blocked: false,
            vip: false,
            password: 'secret1',
        );
    }
}
