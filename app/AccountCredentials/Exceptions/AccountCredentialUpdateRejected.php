<?php

namespace App\AccountCredentials\Exceptions;

use App\AccountCredentials\AccountCredentialBlockReason;
use RuntimeException;

/**
 * Reports a client-safe reason that prevented an account credential update.
 */
final class AccountCredentialUpdateRejected extends RuntimeException
{
    /**
     * Creates a rejection with its safe reason and HTTP status.
     */
    public function __construct(
        public readonly AccountCredentialBlockReason $reason,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($reason->value);
    }
}
