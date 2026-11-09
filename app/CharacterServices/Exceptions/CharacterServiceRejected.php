<?php

namespace App\CharacterServices\Exceptions;

use App\CharacterServices\CharacterServiceBlockReason;
use RuntimeException;

/**
 * Carries one safe service rejection reason across HTTP and persistence layers.
 */
final class CharacterServiceRejected extends RuntimeException
{
    /**
     * Creates one rejection with its intended HTTP status.
     */
    public function __construct(
        public readonly CharacterServiceBlockReason $reason,
        public readonly int $status = 422,
    ) {
        parent::__construct($reason->value);
    }
}
