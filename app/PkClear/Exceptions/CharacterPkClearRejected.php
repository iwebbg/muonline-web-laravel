<?php

namespace App\PkClear\Exceptions;

use App\PkClear\PkClearBlockReason;
use RuntimeException;

/**
 * Reports one safe PK clear rejection to the HTTP boundary.
 */
final class CharacterPkClearRejected extends RuntimeException
{
    /**
     * Creates a rejection with its stable reason and response status.
     */
    public function __construct(
        public readonly PkClearBlockReason $reason,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($reason->value);
    }
}
