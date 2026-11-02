<?php

namespace App\AccountRegistration\Exceptions;

use App\AccountRegistration\AccountRegistrationBlockReason;
use RuntimeException;

/**
 * Reports a client-safe reason that prevented game-account registration.
 */
final class AccountRegistrationRejected extends RuntimeException
{
    /**
     * Creates a rejection with its safe reason and HTTP response status.
     */
    public function __construct(
        public readonly AccountRegistrationBlockReason $reason,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($reason->value);
    }
}
