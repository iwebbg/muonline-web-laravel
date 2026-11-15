<?php

namespace App\Economy;

/**
 * Identifies native game balances without merging them into website Credits.
 */
enum NativeCurrency: string
{
    case WCoinC = 'wcoin_c';
    case WCoinP = 'wcoin_p';
    case GoblinPoints = 'goblin_points';
    case LuckyCoins = 'lucky_coins';
    case Ruud = 'ruud';

    /**
     * Returns the game's familiar currency label for an exchange quote.
     */
    public function label(): string
    {
        return match ($this) {
            self::WCoinC => 'WCoin(C)',
            self::WCoinP => 'WCoin(P)',
            self::GoblinPoints => 'Goblin Points',
            self::LuckyCoins => 'Lucky Coins',
            self::Ruud => 'Ruud',
        };
    }
}
