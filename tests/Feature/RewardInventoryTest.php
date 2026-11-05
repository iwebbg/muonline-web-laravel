<?php

namespace Tests\Feature;

use App\Models\AccountReward;
use App\Shop\AccountRewardStatus;
use App\Shop\Actions\OpenAccountReward;
use App\Shop\Exceptions\RewardOpenRejected;
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
}
