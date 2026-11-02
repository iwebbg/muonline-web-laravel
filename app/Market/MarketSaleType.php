<?php

namespace App\Market;

/**
 * Identifies whether escrow closes at a fixed price or through timed bidding.
 */
enum MarketSaleType: string
{
    case Fixed = 'fixed';
    case Auction = 'auction';
}
