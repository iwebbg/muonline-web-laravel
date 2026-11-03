<?php

namespace App\Market;

/**
 * Identifies where a completed market escrow item was safely deposited.
 */
enum MarketStorageDestination: string
{
    case GameVault = 'game_vault';
    case WebVault = 'web_vault';
}
