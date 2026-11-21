<?php

namespace App\Economy;

/**
 * Identifies an account-wide native service paid for with website Credits.
 */
enum NativePurchaseKind: string
{
    case CurrencyExchange = 'currency_exchange';
    case AccountVip = 'account_vip';
}
