<?php

namespace App\Shop\Actions;

use App\Models\AccountReward;
use App\Shop\AccountRewardStatus;
use App\Shop\Exceptions\RewardOpenRejected;
use App\Shop\MysteryBoxRewardMode;
use App\Shop\RewardOpenBlockReason;
use App\Shop\ShopOfferType;
use App\Shop\WeightedRewardSelector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        $result = DB::transaction(function () use ($accountId, $reward): array {
            $lockedReward = AccountReward::query()
                ->whereKey($reward->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedReward instanceof AccountReward || $lockedReward->account_id !== $accountId) {
                throw new RewardOpenRejected(RewardOpenBlockReason::NotOwned);
            }

            if ($lockedReward->status !== AccountRewardStatus::Sealed) {
                return ['reward' => $lockedReward, 'released_rewards' => []];
            }

            $rewardPool = $lockedReward->reward_pool_snapshot;

            if ($lockedReward->mystery_box_reward_mode === MysteryBoxRewardMode::AllItems) {
                return $this->releaseAllItems($lockedReward, $rewardPool);
            }

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

            return ['reward' => $lockedReward->refresh(), 'released_rewards' => []];
        }, attempts: 3);

        /** @var AccountReward $openedReward */
        $openedReward = $result['reward'];

        if ($openedReward->status === AccountRewardStatus::Ready) {
            $this->fulfillAccountReward->execute($openedReward);
        }

        if ($result['released_rewards'] !== []) {
            $allItemsDelivered = true;

            foreach ($result['released_rewards'] as $releasedReward) {
                $allItemsDelivered = $this->fulfillAccountReward->execute($releasedReward) && $allItemsDelivered;
            }

            $openedReward->update([
                'last_delivery_error' => $allItemsDelivered ? null : 'bundle_delivery_pending',
            ]);
        }

        return $openedReward->refresh();
    }

    /**
     * Consumes one bundle box and creates an independently deliverable reward per item.
     *
     * @param  list<array<string, mixed>>  $rewardPool
     * @return array{reward: AccountReward, released_rewards: list<AccountReward>}
     */
    private function releaseAllItems(AccountReward $lockedReward, array $rewardPool): array
    {
        if ($rewardPool === []) {
            throw new RewardOpenRejected(RewardOpenBlockReason::EmptyRewardPool);
        }

        $releasedRewards = [];

        foreach ($rewardPool as $index => $item) {
            $releasedRewards[] = AccountReward::query()->create([
                'public_id' => (string) Str::uuid(),
                'account_id' => $lockedReward->account_id,
                'shop_offer_id' => $lockedReward->shop_offer_id,
                'source' => $lockedReward->source,
                'source_reference' => $lockedReward->public_id.':item:'.$index,
                'granted_by_administrator_id' => $lockedReward->granted_by_administrator_id,
                'source_reason' => $lockedReward->source_reason,
                'status' => AccountRewardStatus::Ready,
                'reward_type' => ShopOfferType::Item,
                'mystery_box_reward_mode' => MysteryBoxRewardMode::OneRandom,
                'class_scope' => $lockedReward->class_scope,
                'title_en' => $lockedReward->title_en,
                'title_bg' => $lockedReward->title_bg,
                'description_en' => $lockedReward->description_en,
                'description_bg' => $lockedReward->description_bg,
                'reward_pool_snapshot' => [$item],
                'selected_item_snapshot' => $item,
                'opened_at' => now(),
            ]);
        }

        $lockedReward->update([
            'status' => AccountRewardStatus::Claimed,
            'opened_at' => now(),
            'claimed_at' => now(),
        ]);

        return [
            'reward' => $lockedReward->refresh(),
            'released_rewards' => $releasedRewards,
        ];
    }
}
