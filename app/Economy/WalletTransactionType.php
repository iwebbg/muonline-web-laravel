<?php

namespace App\Economy;

/**
 * Identifies why an immutable wallet transaction changed a balance.
 */
enum WalletTransactionType: string
{
    case AdminCredit = 'admin_credit';
    case AdminDebit = 'admin_debit';
    case Reward = 'reward';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case AuctionHold = 'auction_hold';
    case AuctionRelease = 'auction_release';
    case Refund = 'refund';
    case Payment = 'payment';
}
