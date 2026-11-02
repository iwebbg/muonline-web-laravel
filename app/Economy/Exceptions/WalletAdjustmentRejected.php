<?php

namespace App\Economy\Exceptions;

use App\Economy\WalletAdjustmentBlockReason;
use RuntimeException;

/**
 * Reports one safe, typed rejection from the wallet write boundary.
 */
final class WalletAdjustmentRejected extends RuntimeException
{
    /**
     * Creates a rejection carrying its stable public reason.
     */
    public function __construct(
        public readonly WalletAdjustmentBlockReason $reason,
    ) {
        parent::__construct($reason->value);
    }
}
