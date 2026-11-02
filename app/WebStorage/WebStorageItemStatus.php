<?php

namespace App\WebStorage;

/**
 * Identifies one recoverable web storage item lifecycle state.
 */
enum WebStorageItemStatus: string
{
    case Storing = 'storing';
    case Available = 'available';
    case WithdrawalPending = 'withdrawal_pending';
    case Withdrawn = 'withdrawn';
    case Failed = 'failed';
}
