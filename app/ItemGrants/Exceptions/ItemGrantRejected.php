<?php

namespace App\ItemGrants\Exceptions;

use App\ItemGrants\ItemGrantBlockReason;
use RuntimeException;

/**
 * Reports one safe rejection reason from the item-grant write boundary.
 */
final class ItemGrantRejected extends RuntimeException
{
    /**
     * Creates a rejection with the administrator endpoint status code.
     */
    public function __construct(
        public readonly ItemGrantBlockReason $reason,
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($reason->value);
    }
}
