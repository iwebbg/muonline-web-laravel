<?php

namespace App\ZenBank;

/**
 * Identifies the recoverable lifecycle of one Zen Bank transfer.
 */
enum ZenBankOperationStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Conflict = 'conflict';
}
