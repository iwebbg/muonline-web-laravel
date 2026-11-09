<?php

namespace App\CreditEarning;

/**
 * Tracks one recoverable item-to-Credits exchange across both databases.
 */
enum CreditExchangeStatus: string
{
    case Pending = 'pending';
    case Extracted = 'extracted';
    case Completed = 'completed';
    case Failed = 'failed';
}
