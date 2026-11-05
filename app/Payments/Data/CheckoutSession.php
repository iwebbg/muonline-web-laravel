<?php

namespace App\Payments\Data;

/** Carries the provider checkout reference returned to the browser. */
final readonly class CheckoutSession
{
    public function __construct(
        public string $providerOrderId,
        public string $checkoutUrl,
        public array $providerPayload = [],
    ) {}
}
