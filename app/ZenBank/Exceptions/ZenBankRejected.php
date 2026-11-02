<?php

namespace App\ZenBank\Exceptions;

use App\ZenBank\ZenBankBlockReason;
use RuntimeException;

/**
 * Carries one safe Zen Bank rejection reason across application boundaries.
 */
final class ZenBankRejected extends RuntimeException
{
    /**
     * Creates an exception without exposing database details.
     */
    public function __construct(public readonly ZenBankBlockReason $reason)
    {
        parent::__construct($reason->value);
    }
}
