<?php

namespace App\Shop;

/**
 * Identifies the business operation that granted a reward to an account.
 */
enum RewardSource: string
{
    case ShopPurchase = 'shop_purchase';
    case Administrator = 'administrator';
    case Event = 'event';
}
