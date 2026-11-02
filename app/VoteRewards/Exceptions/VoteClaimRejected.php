<?php

namespace App\VoteRewards\Exceptions;

use App\VoteRewards\VoteClaimBlockReason;
use RuntimeException;

/**
 * Reports one expected vote claim policy rejection to the HTTP boundary.
 */
final class VoteClaimRejected extends RuntimeException
{
    /**
     * Creates a rejection carrying its stable localized reason key.
     */
    public function __construct(public readonly VoteClaimBlockReason $reason)
    {
        parent::__construct($reason->value);
    }
}
