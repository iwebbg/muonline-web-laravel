<?php

namespace App\StatReset\Exceptions;

use App\StatReset\StatResetBlockReason;
use RuntimeException;

/**
 * Reports one safe stat-reset rejection to the HTTP boundary.
 */
final class CharacterStatResetRejected extends RuntimeException
{
    /**
     * Creates a rejection with its stable reason and response status.
     */
    public function __construct(
        public readonly StatResetBlockReason $reason,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($reason->value);
    }
}
