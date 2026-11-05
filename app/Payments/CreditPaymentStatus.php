<?php

namespace App\Payments;

/** Identifies the durable state of one Credits payment. */
enum CreditPaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
