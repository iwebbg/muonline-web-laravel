<?php

namespace App\Market;

/**
 * Identifies an idempotent game-database escrow projection outcome.
 */
enum MarketEscrowResult: string
{
    case Extracted = 'extracted';
    case AlreadyExtracted = 'already_extracted';
    case Deposited = 'deposited';
    case AlreadyDeposited = 'already_deposited';
}
