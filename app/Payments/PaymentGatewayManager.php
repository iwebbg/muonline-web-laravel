<?php

namespace App\Payments;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\Gateways\PaypalGateway;
use App\Payments\Gateways\RevolutGateway;
use App\Payments\Gateways\RevolutTagGateway;
use App\Payments\Gateways\StripeGateway;

/** Resolves configured payment provider adapters without exposing secrets. */
final readonly class PaymentGatewayManager
{
    /** Receives the supported hosted and manual payment adapters. */
    public function __construct(
        private StripeGateway $stripe,
        private PaypalGateway $paypal,
        private RevolutGateway $revolut,
        private RevolutTagGateway $revolutTag,
    ) {}

    /** Resolves the adapter responsible for one method. */
    public function for(PaymentProvider $provider): PaymentGateway
    {
        return match ($provider) {
            PaymentProvider::Stripe => $this->stripe,
            PaymentProvider::Paypal => $this->paypal,
            PaymentProvider::Revolut => $this->revolut,
            PaymentProvider::RevolutTag => $this->revolutTag,
        };
    }

    /**
     * Returns only the enabled methods whose configuration is complete.
     *
     * @return list<PaymentProvider>
     */
    public function enabledProviders(): array
    {
        return array_values(array_filter(
            PaymentProvider::cases(),
            fn (PaymentProvider $provider): bool => $this->for($provider)->isEnabled(),
        ));
    }
}
