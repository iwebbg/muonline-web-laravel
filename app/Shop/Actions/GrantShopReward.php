<?php

namespace App\Shop\Actions;

use App\Game\Contracts\GameAccountLookupRepository;
use App\Models\AccountReward;
use App\Models\Administrator;
use App\Models\ShopOffer;
use App\Shop\AccountRewardStatus;
use App\Shop\Exceptions\RewardGrantRejected;
use App\Shop\RewardSource;
use App\Shop\ShopOfferType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Grants one current offer snapshot without charging the target account.
 */
final readonly class GrantShopReward
{
    /**
     * Creates the action with the legacy account existence boundary.
     */
    public function __construct(
        private GameAccountLookupRepository $accountLookup,
        private FulfillAccountReward $fulfillAccountReward,
    ) {}

    /**
     * Creates durable reward ownership and attempts delivery for known items.
     */
    public function execute(
        Administrator $administrator,
        string $accountId,
        ShopOffer $offer,
        string $reason,
        string $idempotencyKey,
    ): AccountReward {
        if (! $this->accountLookup->exists($accountId)) {
            throw new RewardGrantRejected('The target account does not exist.');
        }

        $existingReward = AccountReward::query()
            ->where('source', RewardSource::Administrator)
            ->where('source_reference', $idempotencyKey)
            ->first();

        if ($existingReward instanceof AccountReward) {
            if ($existingReward->account_id !== $accountId || $existingReward->shop_offer_id !== $offer->getKey()) {
                throw new RewardGrantRejected('The confirmation identifier was already used.');
            }

            return $existingReward;
        }

        $offer->loadMissing('items');
        $rewardPool = $offer->items->map(static fn ($item): array => $item->snapshot())->values()->all();

        if ($rewardPool === []) {
            throw new RewardGrantRejected('The offer does not contain a reward item.');
        }

        $isMysteryBox = $offer->type === ShopOfferType::MysteryBox;
        $reward = DB::transaction(fn (): AccountReward => AccountReward::query()->create([
            'public_id' => (string) Str::uuid(),
            'account_id' => $accountId,
            'shop_offer_id' => $offer->getKey(),
            'source' => RewardSource::Administrator,
            'source_reference' => $idempotencyKey,
            'granted_by_administrator_id' => $administrator->getKey(),
            'source_reason' => $reason,
            'status' => $isMysteryBox ? AccountRewardStatus::Sealed : AccountRewardStatus::Ready,
            'reward_type' => $offer->type,
            'class_scope' => $offer->class_scope,
            'title_en' => $offer->title_en,
            'title_bg' => $offer->title_bg,
            'description_en' => $offer->description_en,
            'description_bg' => $offer->description_bg,
            'reward_pool_snapshot' => $rewardPool,
            'selected_item_snapshot' => $isMysteryBox ? null : $rewardPool[0],
            'opened_at' => $isMysteryBox ? null : now(),
        ]), attempts: 3);

        if ($reward->status === AccountRewardStatus::Ready) {
            $this->fulfillAccountReward->execute($reward);
        }

        return $reward->refresh();
    }
}
