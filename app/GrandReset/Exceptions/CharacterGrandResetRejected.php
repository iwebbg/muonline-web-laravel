<?php

namespace App\GrandReset\Exceptions;

use App\GrandReset\GrandResetBlockReason;
use RuntimeException;

/**
 * Reports a safe domain reason that prevented a character grand reset.
 */
final class CharacterGrandResetRejected extends RuntimeException
{
    /**
     * Creates a rejection with a client-safe reason and HTTP response status.
     */
    public function __construct(
        public readonly GrandResetBlockReason $reason,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($reason->value);
    }
}
