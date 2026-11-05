<?php

namespace App\Payments\Actions;

use App\Auth\GameAccount;
use App\Models\CreditPackage;
use App\Models\CreditPayment;
use App\Payments\CreditPackageStatus;
use App\Payments\CreditPaymentStatus;
use App\Payments\PaymentGatewayManager;
use App\Payments\PaymentProvider;
use Illuminate\Support\Str;
use RuntimeException;

/** Creates one package-snapshotted payment and its hosted checkout. */
final readonly class CreateCreditCheckout
{
    public function __construct(private PaymentGatewayManager $gateways) {}

    public function execute(GameAccount $account, CreditPackage $package, PaymentProvider $provider): CreditPayment
    {
        if ($package->status !== CreditPackageStatus::Published) {
            throw new RuntimeException('The Credits package is not available.');
        }
        $gateway = $this->gateways->for($provider);

        if (! $gateway->isEnabled()) {
            throw new RuntimeException('The payment provider is not available.');
        }
        $payment = CreditPayment::query()->create([
            'public_id' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'account_id' => $account->username,
            'credit_package_id' => $package->getKey(),
            'package_snapshot' => ['title_en' => $package->title_en, 'title_bg' => $package->title_bg, 'credits' => $package->credits, 'price_minor' => $package->price_minor, 'currency' => $package->currency],
            'provider' => $provider,
            'status' => CreditPaymentStatus::Pending,
            'credits' => $package->credits,
            'amount_minor' => $package->price_minor,
            'currency' => $package->currency,
        ]);

        try {
            $checkout = $gateway->createCheckout($payment);
        } catch (\Throwable $exception) {
            $payment->update(['status' => CreditPaymentStatus::Failed, 'failed_at' => now()]);
            throw $exception;
        }
        $payment->update(['provider_order_id' => $checkout->providerOrderId, 'provider_payload' => ['checkout_url' => $checkout->checkoutUrl, ...$checkout->providerPayload]]);

        return $payment->refresh();
    }
}
