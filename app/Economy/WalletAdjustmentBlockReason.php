<?php

namespace App\Economy;

/**
 * Identifies why an attempted wallet balance transition was rejected.
 */
enum WalletAdjustmentBlockReason: string
{
    case AccountNotFound = 'account_not_found';
    case InvalidAdjustment = 'invalid_adjustment';
    case InsufficientBalance = 'insufficient_balance';
    case IdempotencyConflict = 'idempotency_conflict';
    case BalanceLimitExceeded = 'balance_limit_exceeded';
}
