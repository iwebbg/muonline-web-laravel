<?php

namespace App\Modules;

/**
 * Identifies one independently controllable player-facing server module.
 */
enum ServerModule: string
{
    case Registration = 'registration';
    case PasswordRecovery = 'password_recovery';
    case VoteRewards = 'vote_rewards';
    case Vip = 'vip';
    case WebStorage = 'web_storage';
    case JewelBank = 'jewel_bank';
    case ZenBank = 'zen_bank';
    case Market = 'market';
    case Auction = 'auction';
}
