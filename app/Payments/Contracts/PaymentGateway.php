<?php

namespace App\Payments\Contracts;

use App\Models\CreditPayment;
use App\Payments\Data\CheckoutSession;
use App\Payments\PaymentProvider;
use Illuminate\Http\Request;

/** Defines hosted checkout and signed confirmation behavior for one provider. */
interface PaymentGateway
{
    public function provider(): PaymentProvider;

    public function isEnabled(): bool;

    public function createCheckout(CreditPayment $payment): CheckoutSession;

    /** @return array<string, mixed> */
    public function confirmReturn(CreditPayment $payment, Request $request): array;

    /** @return array<string, mixed> */
    public function verifiedWebhook(Request $request): array;
}
