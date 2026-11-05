<?php

namespace App\Shop;

/**
 * Identifies the custody destination that received an exact reward item.
 */
enum RewardDeliveryDestination: string
{
    case GameVault = 'game_vault';
    case WebVault = 'web_vault';
}
