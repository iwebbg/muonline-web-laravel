<?php

namespace App\Moderation\Exceptions;

use App\Moderation\ModerationBlockReason;
use RuntimeException;

/**
 * Reports a safe, stable reason for rejecting one moderation operation.
 */
final class ModerationRejected extends RuntimeException
{
    /**
     * Creates a rejection with the HTTP status exposed by the administrator endpoint.
     */
    public function __construct(
        public readonly ModerationBlockReason $reason,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($reason->value);
    }
}
