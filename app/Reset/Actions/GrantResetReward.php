<?php

namespace App\Reset\Actions;

use App\Economy\Actions\AdjustWalletBalance;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Models\AccountReward;
use App\Models\CharacterGrandResetOperation;
use App\Models\CharacterResetOperation;
use App\Models\ShopOffer;
use App\Reset\ResetRewardType;
use App\Shop\AccountRewardStatus;
use App\Shop\RewardSource;
use App\Shop\ShopOfferType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

/**
 * Grants the policy snapshot reward exactly once after a completed reset operation.
 */
final readonly class GrantResetReward
{
    /**
     * Creates the reward action with the append-only wallet boundary.
     */
    public function __construct(
        private AdjustWalletBalance $adjustWalletBalance,
    ) {}

    /**
     * Grants the normal-reset reward stored with one completed operation.
     */
    public function forReset(CharacterResetOperation $operation): void
    {
        try {
            $this->grant(
                operation: $operation,
                rewardTypeKey: 'reset_reward_type',
                rewardCreditsKey: 'reset_reward_credits',
                rewardOfferKey: 'reset_reward_shop_offer_id',
                sourceReference: 'character-reset:'.$operation->getKey(),
                reason: 'Character reset reward: '.$operation->character_name,
            );
        } catch (Throwable $exception) {
            $operation->update(['reward_failure_code' => 'reward_grant_failed']);

            throw $exception;
        }
    }

    /**
     * Grants the grand-reset reward stored with one completed operation.
     */
    public function forGrandReset(CharacterGrandResetOperation $operation): void
    {
        try {
            $this->grant(
                operation: $operation,
                rewardTypeKey: 'grand_reset_reward_type',
                rewardCreditsKey: 'grand_reset_reward_credits',
                rewardOfferKey: 'grand_reset_reward_shop_offer_id',
                sourceReference: 'character-grand-reset:'.$operation->getKey(),
                reason: 'Character grand reset reward: '.$operation->character_name,
            );
        } catch (Throwable $exception) {
            $operation->update(['reward_failure_code' => 'reward_grant_failed']);

            throw $exception;
        }
    }

    /**
     * Applies one typed snapshot reward and links its immutable ledger record to the operation.
     */
    private function grant(
        Model $operation,
        string $rewardTypeKey,
        string $rewardCreditsKey,
        string $rewardOfferKey,
        string $sourceReference,
        string $reason,
    ): void {
        $snapshot = $operation->settings_snapshot;
        $rewardType = ResetRewardType::tryFrom((string) ($snapshot[$rewardTypeKey] ?? ''))
            ?? ResetRewardType::None;

        if ($rewardType === ResetRewardType::None) {
            return;
        }

        if ($rewardType === ResetRewardType::Credits) {
            $transaction = $this->adjustWalletBalance->execute(
                accountId: (string) $operation->account_id,
                currency: WalletCurrency::Credits,
                type: WalletTransactionType::Reward,
                amount: (int) ($snapshot[$rewardCreditsKey] ?? 0),
                reason: $reason,
                administratorId: null,
                idempotencyKey: Uuid::uuid5(
                    Uuid::NAMESPACE_URL,
                    'muonline-'.$sourceReference.'-credits',
                )->toString(),
            );

            $operation->update([
                'reward_wallet_transaction_id' => $transaction->getKey(),
                'reward_failure_code' => null,
            ]);

            return;
        }

        $offerId = (int) ($snapshot[$rewardOfferKey] ?? 0);
        $reward = $this->grantMysteryBox(
            accountId: (string) $operation->account_id,
            offerId: $offerId,
            sourceReference: $sourceReference,
            reason: $reason,
        );

        $operation->update([
            'account_reward_id' => $reward->getKey(),
            'reward_failure_code' => null,
        ]);
    }

    /**
     * Creates sealed ownership from the selected Mystery Box snapshot exactly once.
     */
    private function grantMysteryBox(
        string $accountId,
        int $offerId,
        string $sourceReference,
        string $reason,
    ): AccountReward {
        $existingReward = AccountReward::query()
            ->where('source', RewardSource::Event)
            ->where('source_reference', $sourceReference)
            ->first();

        if ($existingReward instanceof AccountReward) {
            return $existingReward;
        }

        $offer = ShopOffer::query()
            ->with('items')
            ->whereKey($offerId)
            ->where('type', ShopOfferType::MysteryBox)
            ->first();

        if (! $offer instanceof ShopOffer || $offer->items->isEmpty()) {
            throw new RuntimeException('The configured reset Mystery Box is unavailable.');
        }

        $rewardPool = $offer->items
            ->map(static fn ($item): array => $item->snapshot())
            ->values()
            ->all();

        return DB::transaction(fn (): AccountReward => AccountReward::query()->firstOrCreate(
            [
                'source' => RewardSource::Event,
                'source_reference' => $sourceReference,
            ],
            [
                'public_id' => (string) Str::uuid(),
                'account_id' => $accountId,
                'shop_offer_id' => $offer->getKey(),
                'source_reason' => $reason,
                'status' => AccountRewardStatus::Sealed,
                'reward_type' => ShopOfferType::MysteryBox,
                'mystery_box_reward_mode' => $offer->mystery_box_reward_mode,
                'class_scope' => $offer->class_scope,
                'title_en' => $offer->title_en,
                'title_bg' => $offer->title_bg,
                'description_en' => $offer->description_en,
                'description_bg' => $offer->description_bg,
                'reward_pool_snapshot' => $rewardPool,
                'selected_item_snapshot' => null,
            ],
        ), attempts: 3);
    }
}
