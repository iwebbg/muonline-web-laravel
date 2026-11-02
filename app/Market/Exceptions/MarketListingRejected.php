<?php

namespace App\Market\Exceptions;

use App\Market\MarketListingBlockReason;
use RuntimeException;

/**
 * Reports one typed rejection from the recoverable market escrow boundary.
 */
final class MarketListingRejected extends RuntimeException
{
    /**
     * Creates a rejection carrying its stable public reason.
     */
    public function __construct(
        public readonly MarketListingBlockReason $reason,
    ) {
        parent::__construct($reason->value);
    }
}
