<?php

namespace App\VoteRewards;

/**
 * Identifies why a vote claim or reward operation was refused.
 */
enum VoteClaimBlockReason: string
{
    case SiteDisabled = 'site_disabled';
    case PendingClaim = 'pending_claim';
    case AccountCooldown = 'account_cooldown';
    case IpCooldown = 'ip_cooldown';
    case IdempotencyConflict = 'idempotency_conflict';
    case AlreadyReviewed = 'already_reviewed';
    case ClaimExpired = 'claim_expired';
    case InvalidReward = 'invalid_reward';
    case AutomaticRewardDisabled = 'automatic_reward_disabled';
    case BalanceLimitExceeded = 'balance_limit_exceeded';
    case PermissionDenied = 'permission_denied';
}
