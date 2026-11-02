<?php

namespace App\VoteRewards\Actions;

use App\Admin\Authorization\AdministratorPermission;
use App\Economy\Actions\AdjustWalletBalance;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Models\AccountZenBalance;
use App\Models\Administrator;
use App\Models\VoteClaim;
use App\Models\WalletTransaction;
use App\VoteRewards\Exceptions\VoteClaimRejected;
use App\VoteRewards\VoteClaimBlockReason;
use App\VoteRewards\VoteClaimStatus;
use Illuminate\Support\Facades\DB;

/**
 * Settles one verified vote claim into Credits and Zen ledgers atomically.
 */
final readonly class AwardVoteClaim
{
    private const MAX_ZEN_BALANCE = 9_000_000_000_000_000;

    /**
     * Creates the action with the existing append-only Credits ledger boundary.
     */
    public function __construct(private AdjustWalletBalance $adjustWalletBalance) {}

    /**
     * Awards the snapshotted reward after checking reviewer permission and claim state.
     *
     * @throws VoteClaimRejected When the claim cannot be safely rewarded.
     */
    public function execute(VoteClaim $voteClaim, Administrator $administrator): VoteClaim
    {
        if (! $administrator->hasPermission(AdministratorPermission::ManageEconomy)) {
            throw new VoteClaimRejected(VoteClaimBlockReason::PermissionDenied);
        }

        $reviewedClaim = DB::transaction(function () use ($voteClaim, $administrator): VoteClaim {
            $lockedClaim = VoteClaim::query()
                ->with('site')
                ->lockForUpdate()
                ->findOrFail($voteClaim->getKey());

            if ($lockedClaim->status !== VoteClaimStatus::Pending) {
                throw new VoteClaimRejected(VoteClaimBlockReason::AlreadyReviewed);
            }

            if ($lockedClaim->expires_at->isPast()) {
                $lockedClaim->update([
                    'status' => VoteClaimStatus::Expired,
                    'reviewed_at' => now(),
                    'reviewed_by_administrator_id' => $administrator->getKey(),
                    'rejection_reason' => VoteClaimBlockReason::ClaimExpired->value,
                ]);

                return $lockedClaim->refresh();
            }

            $walletTransaction = $this->awardCredits($lockedClaim, $administrator);
            [$zenBalanceBefore, $zenBalanceAfter] = $this->awardZen($lockedClaim);

            $lockedClaim->update([
                'status' => VoteClaimStatus::Rewarded,
                'wallet_transaction_id' => $walletTransaction?->getKey(),
                'zen_balance_before' => $zenBalanceBefore,
                'zen_balance_after' => $zenBalanceAfter,
                'reviewed_by_administrator_id' => $administrator->getKey(),
                'reviewed_at' => now(),
                'rewarded_at' => now(),
                'rejection_reason' => null,
            ]);

            return $lockedClaim->refresh();
        }, attempts: 3);

        if ($reviewedClaim->status === VoteClaimStatus::Expired) {
            throw new VoteClaimRejected(VoteClaimBlockReason::ClaimExpired);
        }

        return $reviewedClaim;
    }

    /**
     * Creates the idempotent Credits ledger entry when the claim includes Credits.
     */
    private function awardCredits(
        VoteClaim $voteClaim,
        Administrator $administrator,
    ): ?WalletTransaction {
        if ($voteClaim->reward_credits === 0) {
            return null;
        }

        return $this->adjustWalletBalance->execute(
            accountId: $voteClaim->account_id,
            currency: WalletCurrency::Credits,
            type: WalletTransactionType::Reward,
            amount: $voteClaim->reward_credits,
            reason: 'Verified vote reward for site '.$voteClaim->vote_site_id,
            administratorId: (int) $administrator->getKey(),
            idempotencyKey: $voteClaim->public_id,
        );
    }

    /**
     * Adds the Zen reward to the recoverable web balance and returns its audit values.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function awardZen(VoteClaim $voteClaim): array
    {
        if ($voteClaim->reward_zen === 0) {
            return [null, null];
        }

        AccountZenBalance::query()->insertOrIgnore([
            'account_id' => $voteClaim->account_id,
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $zenBalance = AccountZenBalance::query()
            ->where('account_id', $voteClaim->account_id)
            ->lockForUpdate()
            ->firstOrFail();
        $balanceBefore = $zenBalance->balance;
        $balanceAfter = $balanceBefore + $voteClaim->reward_zen;

        if ($balanceAfter > self::MAX_ZEN_BALANCE) {
            throw new VoteClaimRejected(VoteClaimBlockReason::BalanceLimitExceeded);
        }

        $zenBalance->update(['balance' => $balanceAfter]);

        return [$balanceBefore, $balanceAfter];
    }
}
