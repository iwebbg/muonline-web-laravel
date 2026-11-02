<?php

namespace App\VoteRewards\Actions;

use App\Models\VoteClaim;
use App\Models\VoteSite;
use App\VoteRewards\Exceptions\VoteClaimRejected;
use App\VoteRewards\VoteClaimBlockReason;
use App\VoteRewards\VoteClaimStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opens one reviewable vote claim after enforcing account and network cooldowns.
 */
final readonly class CreateVoteClaim
{
    /**
     * Creates or replays one claim while snapshotting its reward policy.
     *
     * @throws VoteClaimRejected When the site or cooldown policy refuses the visit.
     */
    public function execute(
        VoteSite $voteSite,
        string $accountId,
        string $ipAddress,
        string $idempotencyKey,
    ): VoteClaim {
        $ipHash = $this->hashIpAddress($ipAddress);
        $existingClaim = VoteClaim::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingClaim instanceof VoteClaim) {
            return $this->resolveExisting(
                claim: $existingClaim,
                voteSite: $voteSite,
                accountId: $accountId,
                ipHash: $ipHash,
            );
        }

        try {
            return DB::transaction(function () use (
                $voteSite,
                $accountId,
                $ipHash,
                $idempotencyKey,
            ): VoteClaim {
                $lockedSite = VoteSite::query()
                    ->lockForUpdate()
                    ->findOrFail($voteSite->getKey());

                if (! $lockedSite->enabled) {
                    throw new VoteClaimRejected(VoteClaimBlockReason::SiteDisabled);
                }

                if ($lockedSite->reward_credits === 0 && $lockedSite->reward_zen === 0) {
                    throw new VoteClaimRejected(VoteClaimBlockReason::InvalidReward);
                }

                $this->expireStaleClaims($lockedSite, $accountId);
                $this->ensureNoPendingClaim($lockedSite, $accountId);
                $this->ensureCooldownExpired($lockedSite, $accountId, $ipHash);

                return VoteClaim::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'idempotency_key' => $idempotencyKey,
                    'vote_site_id' => $lockedSite->getKey(),
                    'account_id' => $accountId,
                    'ip_hash' => $ipHash,
                    'reward_credits' => $lockedSite->reward_credits,
                    'reward_zen' => $lockedSite->reward_zen,
                    'status' => VoteClaimStatus::Pending,
                    'initiated_at' => now(),
                    'expires_at' => now()->addDay(),
                ]);
            }, attempts: 3);
        } catch (QueryException $exception) {
            $existingClaim = VoteClaim::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingClaim instanceof VoteClaim) {
                return $this->resolveExisting(
                    claim: $existingClaim,
                    voteSite: $voteSite,
                    accountId: $accountId,
                    ipHash: $ipHash,
                );
            }

            throw $exception;
        }
    }

    /**
     * Marks abandoned pending claims as expired before a new visit is considered.
     */
    private function expireStaleClaims(VoteSite $voteSite, string $accountId): void
    {
        VoteClaim::query()
            ->where('vote_site_id', $voteSite->getKey())
            ->where('account_id', $accountId)
            ->where('status', VoteClaimStatus::Pending)
            ->where('expires_at', '<=', now())
            ->update([
                'status' => VoteClaimStatus::Expired,
                'reviewed_at' => now(),
                'rejection_reason' => VoteClaimBlockReason::ClaimExpired->value,
            ]);
    }

    /**
     * Prevents multiple simultaneous reviews for one account and vote site.
     */
    private function ensureNoPendingClaim(VoteSite $voteSite, string $accountId): void
    {
        $hasPendingClaim = VoteClaim::query()
            ->where('vote_site_id', $voteSite->getKey())
            ->where('account_id', $accountId)
            ->where('status', VoteClaimStatus::Pending)
            ->where('expires_at', '>', now())
            ->exists();

        if ($hasPendingClaim) {
            throw new VoteClaimRejected(VoteClaimBlockReason::PendingClaim);
        }
    }

    /**
     * Applies the configured cooldown independently to account and hashed IP.
     */
    private function ensureCooldownExpired(
        VoteSite $voteSite,
        string $accountId,
        string $ipHash,
    ): void {
        $cooldownStartedAfter = now()->subHours($voteSite->cooldown_hours);
        $accountIsCoolingDown = VoteClaim::query()
            ->where('vote_site_id', $voteSite->getKey())
            ->where('account_id', $accountId)
            ->where('status', VoteClaimStatus::Rewarded)
            ->where('rewarded_at', '>', $cooldownStartedAfter)
            ->exists();

        if ($accountIsCoolingDown) {
            throw new VoteClaimRejected(VoteClaimBlockReason::AccountCooldown);
        }

        $ipIsCoolingDown = VoteClaim::query()
            ->where('vote_site_id', $voteSite->getKey())
            ->where('ip_hash', $ipHash)
            ->where('status', VoteClaimStatus::Rewarded)
            ->where('rewarded_at', '>', $cooldownStartedAfter)
            ->exists();

        if ($ipIsCoolingDown) {
            throw new VoteClaimRejected(VoteClaimBlockReason::IpCooldown);
        }
    }

    /**
     * Returns an identical idempotent replay or refuses cross-request key reuse.
     */
    private function resolveExisting(
        VoteClaim $claim,
        VoteSite $voteSite,
        string $accountId,
        string $ipHash,
    ): VoteClaim {
        $isIdentical = $claim->vote_site_id === $voteSite->getKey()
            && hash_equals($claim->account_id, $accountId)
            && hash_equals($claim->ip_hash, $ipHash);

        if (! $isIdentical) {
            throw new VoteClaimRejected(VoteClaimBlockReason::IdempotencyConflict);
        }

        return $claim;
    }

    /**
     * Produces a stable privacy-preserving network key using the application secret.
     */
    private function hashIpAddress(string $ipAddress): string
    {
        return hash_hmac('sha256', $ipAddress, (string) config('app.key'));
    }
}
