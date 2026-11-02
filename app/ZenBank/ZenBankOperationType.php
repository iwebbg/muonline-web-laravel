<?php

namespace App\ZenBank;

/**
 * Identifies the direction of one physical warehouse Zen transfer.
 */
enum ZenBankOperationType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
}
