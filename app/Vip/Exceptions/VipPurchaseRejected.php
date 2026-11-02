<?php

namespace App\Vip\Exceptions;

use App\Vip\VipPurchaseBlockReason;
use RuntimeException;

/**
 * Reports one typed rejection from the character VIP purchase boundary.
 */
final class VipPurchaseRejected extends RuntimeException
{
    /**
     * Creates a rejection carrying its stable public reason.
     */
    public function __construct(
        public readonly VipPurchaseBlockReason $reason,
    ) {
        parent::__construct($reason->value);
    }
}
