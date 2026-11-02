<?php

namespace App\CharacterMove\Exceptions;

use App\CharacterMove\CharacterMoveBlockReason;
use RuntimeException;

/**
 * Reports one expected rejection from the locked Move to Town checks.
 */
final class CharacterMoveRejected extends RuntimeException
{
    /**
     * Creates a rejection with a stable reason and response status.
     */
    public function __construct(
        public readonly CharacterMoveBlockReason $reason,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($reason->value);
    }
}
