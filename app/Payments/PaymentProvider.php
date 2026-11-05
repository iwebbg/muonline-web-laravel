<?php

namespace App\Payments;

/** Identifies one supported hosted Credits payment provider. */
enum PaymentProvider: string
{
    case Stripe = 'stripe';
    case Paypal = 'paypal';
    case Revolut = 'revolut';
}
