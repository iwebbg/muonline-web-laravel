<?php

namespace App\WebStorage\Exceptions;

use App\WebStorage\WebStorageBlockReason;
use RuntimeException;

/**
 * Reports one safe typed rejection from the web storage boundary.
 */
final class WebStorageRejected extends RuntimeException
{
    /**
     * Creates a rejection carrying its stable public reason.
     */
    public function __construct(
        public readonly WebStorageBlockReason $reason,
    ) {
        parent::__construct($reason->value);
    }
}
