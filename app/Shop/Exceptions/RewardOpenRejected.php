<?php

namespace App\Shop\Exceptions;

use App\Shop\RewardOpenBlockReason;
use RuntimeException;

/**
 * Carries a safe reward-opening rejection across the HTTP boundary.
 */
final class RewardOpenRejected extends RuntimeException
{
    /**
     * Creates an exception with its public rejection reason.
     */
    public function __construct(public readonly RewardOpenBlockReason $reason) {}
}
