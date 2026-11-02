<?php

namespace App\VoteRewards\Actions;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use App\Models\VoteClaim;
use App\VoteRewards\Exceptions\VoteClaimRejected;
use App\VoteRewards\VoteClaimBlockReason;
use App\VoteRewards\VoteClaimStatus;
use Illuminate\Support\Facades\DB;

/**
 * Records an administrator's refusal of one unverified vote claim.
 */
final readonly class RejectVoteClaim
{
    /**
     * Rejects one pending claim without changing either player balance.
     *
     * @throws VoteClaimRejected When the reviewer or claim state is invalid.
     */
    public function execute(
        VoteClaim $voteClaim,
        Administrator $administrator,
        string $reason,
    ): VoteClaim {
        if (! $administrator->hasPermission(AdministratorPermission::ManageEconomy)) {
            throw new VoteClaimRejected(VoteClaimBlockReason::PermissionDenied);
        }

        return DB::transaction(function () use (
            $voteClaim,
            $administrator,
            $reason,
        ): VoteClaim {
            $lockedClaim = VoteClaim::query()
                ->lockForUpdate()
                ->findOrFail($voteClaim->getKey());

            if ($lockedClaim->status !== VoteClaimStatus::Pending) {
                throw new VoteClaimRejected(VoteClaimBlockReason::AlreadyReviewed);
            }

            $lockedClaim->update([
                'status' => VoteClaimStatus::Rejected,
                'reviewed_by_administrator_id' => $administrator->getKey(),
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return $lockedClaim->refresh();
        }, attempts: 3);
    }
}
