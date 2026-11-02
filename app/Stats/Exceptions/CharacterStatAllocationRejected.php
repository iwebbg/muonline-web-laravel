<?php

namespace App\Stats\Exceptions;

use App\Stats\StatAllocationBlockReason;
use RuntimeException;

/**
 * Reports a safe domain reason that prevented a character stat mutation.
 */
final class CharacterStatAllocationRejected extends RuntimeException
{
    /**
     * Creates a rejection with a client-safe reason and HTTP response status.
     */
    public function __construct(
        public readonly StatAllocationBlockReason $reason,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($reason->value);
    }
}
