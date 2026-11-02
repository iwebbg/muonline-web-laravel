<?php

namespace App\VoteRewards;

/**
 * Identifies the review and reward state of one vote claim.
 */
enum VoteClaimStatus: string
{
    case Pending = 'pending';
    case Rewarded = 'rewarded';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
