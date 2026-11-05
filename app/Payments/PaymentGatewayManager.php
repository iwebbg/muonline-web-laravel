<?php

namespace App\Payments;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\Gateways\PaypalGateway;
use App\Payments\Gateways\RevolutGateway;
use App\Payments\Gateways\StripeGateway;

/** Resolves configured payment provider adapters without exposing secrets. */
final readonly class PaymentGatewayManager
{
    public function __construct(
        private StripeGateway $stripe,
        private PaypalGateway $paypal,
        private RevolutGateway $revolut,
    ) {}

    public function for(PaymentProvider $provider): PaymentGateway
    {
        return match ($provider) {
            PaymentProvider::Stripe => $this->stripe,
            PaymentProvider::Paypal => $this->paypal,
            PaymentProvider::Revolut => $this->revolut,
        };
    }

    /** @return list<PaymentProvider> */
    public function enabledProviders(): array
    {
        return array_values(array_filter(
            PaymentProvider::cases(),
            fn (PaymentProvider $provider): bool => $this->for($provider)->isEnabled(),
        ));
    }
}
