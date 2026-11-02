<?php

namespace App\Market;

/**
 * Identifies one recoverable player-market escrow lifecycle state.
 */
enum MarketListingStatus: string
{
    case Extracting = 'extracting';
    case Active = 'active';
    case DeliveryPending = 'delivery_pending';
    case Delivered = 'delivered';
    case ReturnPending = 'return_pending';
    case Returned = 'returned';
    case Failed = 'failed';
}
