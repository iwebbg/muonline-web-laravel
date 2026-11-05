<?php

namespace App\Shop;

/**
 * Identifies why an owned reward cannot be opened.
 */
enum RewardOpenBlockReason: string
{
    case NotOwned = 'not_owned';
    case AlreadyOpened = 'already_opened';
    case EmptyRewardPool = 'empty_reward_pool';
}
