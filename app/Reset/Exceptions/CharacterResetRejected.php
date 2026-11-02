<?php

namespace App\Reset\Exceptions;

use App\Reset\ResetBlockReason;
use RuntimeException;

/**
 * Reports a safe domain reason that prevented a character reset mutation.
 */
final class CharacterResetRejected extends RuntimeException
{
    /**
     * Creates a rejection with a client-safe reason and HTTP response status.
     */
    public function __construct(
        public readonly ResetBlockReason $reason,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($reason->value);
    }
}
