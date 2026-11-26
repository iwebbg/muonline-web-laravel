<?php

namespace App\Payments;

/** Identifies one supported hosted Credits payment provider. */
enum PaymentProvider: string
{
    case Stripe = 'stripe';
    case Paypal = 'paypal';
    case Revolut = 'revolut';
    case RevolutTag = 'revolut_tag';

    /** Returns the payment method's player-facing name. */
    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe',
            self::Paypal => 'PayPal',
            self::Revolut => 'Revolut',
            self::RevolutTag => 'Revolut Tag',
        };
    }
}
