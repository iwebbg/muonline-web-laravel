<?php

namespace App\Shop\Actions;

use App\Models\AccountReward;
use App\Shop\AccountRewardStatus;
use App\Shop\Exceptions\RewardOpenRejected;
use App\Shop\RewardOpenBlockReason;
use App\Shop\WeightedRewardSelector;
use Illuminate\Support\Facades\DB;

/**
 * Reveals one owned mystery reward exactly once under a row lock.
 */
final readonly class OpenAccountReward
{
    /**
     * Creates the action with the version-independent weighted selector.
     */
    public function __construct(
        private WeightedRewardSelector $selector,
        private FulfillAccountReward $fulfillAccountReward,
    ) {}

    /**
     * Opens an owned sealed reward or returns its already selected result.
     *
     * @throws RewardOpenRejected When ownership or pool integrity fails.
     */
    public function execute(string $accountId, AccountReward $reward): AccountReward
    {
        $openedReward = DB::transaction(function () use ($accountId, $reward): AccountReward {
            $lockedReward = AccountReward::query()
                ->whereKey($reward->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedReward instanceof AccountReward || $lockedReward->account_id !== $accountId) {
                throw new RewardOpenRejected(RewardOpenBlockReason::NotOwned);
            }

            if ($lockedReward->status !== AccountRewardStatus::Sealed) {
                return $lockedReward;
            }

            $rewardPool = $lockedReward->reward_pool_snapshot;
            $totalWeight = $this->selector->totalWeight($rewardPool);

            if ($totalWeight < 1) {
                throw new RewardOpenRejected(RewardOpenBlockReason::EmptyRewardPool);
            }

            $selectedItem = $this->selector->select($rewardPool, random_int(1, $totalWeight));
            $lockedReward->update([
                'status' => AccountRewardStatus::Ready,
                'selected_item_snapshot' => $selectedItem,
                'opened_at' => now(),
            ]);

            return $lockedReward->refresh();
        }, attempts: 3);

        if ($openedReward->status === AccountRewardStatus::Ready) {
            $this->fulfillAccountReward->execute($openedReward);
        }

        return $openedReward->refresh();
    }
}
