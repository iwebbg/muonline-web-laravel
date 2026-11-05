<?php

namespace App\Shop\Exceptions;

use App\Shop\ShopPurchaseBlockReason;
use RuntimeException;

/**
 * Carries one safe shop purchase rejection across the application boundary.
 */
final class ShopPurchaseRejected extends RuntimeException
{
    /**
     * Creates an exception with its public rejection reason.
     */
    public function __construct(public readonly ShopPurchaseBlockReason $reason) {}
}
