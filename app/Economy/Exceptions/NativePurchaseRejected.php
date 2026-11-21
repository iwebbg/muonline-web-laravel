<?php

namespace App\Economy\Exceptions;

use App\Economy\NativePurchaseBlockReason;
use RuntimeException;

/**
 * Carries an explicit native purchase rejection without exposing SQL details.
 */
final class NativePurchaseRejected extends RuntimeException
{
    /**
     * Creates the domain rejection used by payment coordination and native receipts.
     */
    public function __construct(public readonly NativePurchaseBlockReason $reason)
    {
        parent::__construct($reason->value);
    }
}
