<?php

namespace App\Shop;

/**
 * Identifies the lifecycle state of one account-owned reward.
 */
enum AccountRewardStatus: string
{
    case Sealed = 'sealed';
    case Ready = 'ready';
    case Claimed = 'claimed';
}
