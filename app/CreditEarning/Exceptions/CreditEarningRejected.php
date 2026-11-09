<?php

namespace App\CreditEarning\Exceptions;

use App\CreditEarning\CreditEarningBlockReason;
use RuntimeException;

/**
 * Rejects one Credits earning operation with a stable player-facing reason.
 */
final class CreditEarningRejected extends RuntimeException
{
    /**
     * Creates a rejection with its intended HTTP status.
     */
    public function __construct(
        public readonly CreditEarningBlockReason $reason,
        public readonly int $status = 422,
    ) {
        parent::__construct($reason->value);
    }
}
