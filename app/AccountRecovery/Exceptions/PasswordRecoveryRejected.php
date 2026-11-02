<?php

namespace App\AccountRecovery\Exceptions;

use App\AccountRecovery\PasswordRecoveryBlockReason;
use RuntimeException;

/**
 * Reports a safe failure from a token claim or legacy password update.
 */
final class PasswordRecoveryRejected extends RuntimeException
{
    /**
     * Creates a recovery failure with its endpoint status.
     */
    public function __construct(
        public readonly PasswordRecoveryBlockReason $reason,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($reason->value);
    }
}
