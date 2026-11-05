<?php

namespace App\Payments\Gateways;

use App\Models\CreditPayment;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Data\CheckoutSession;
use App\Payments\PaymentProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Implements Revolut Merchant hosted checkout and HMAC webhook verification. */
final class RevolutGateway implements PaymentGateway
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::Revolut;
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.payments.revolut.enabled')
            && filled(config('services.payments.revolut.secret'))
            && filled(config('services.payments.revolut.webhook_secret'));
    }

    public function createCheckout(CreditPayment $payment): CheckoutSession
    {
        $data = $this->client()->post('/api/orders', [
            'amount' => $payment->amount_minor,
            'currency' => $payment->currency,
            'capture_mode' => 'automatic',
            'merchant_order_ext_ref' => $payment->public_id,
            'description' => $payment->package_snapshot['title_en'],
            'redirect_url' => route('account.credit-payments.return', [$payment, 'provider' => 'revolut']),
            'metadata' => ['credit_payment_id' => $payment->public_id],
        ])->throw()->json();

        if (! isset($data['id'], $data['checkout_url'])) {
            throw new RuntimeException('Revolut did not return a hosted checkout URL.');
        }

        return new CheckoutSession((string) $data['id'], (string) $data['checkout_url']);
    }

    public function confirmReturn(CreditPayment $payment, Request $request): array
    {
        $data = $this->client()->get('/api/orders/'.$payment->provider_order_id)->throw()->json();

        return $this->confirmation($payment, is_array($data) ? $data : []);
    }

    public function verifiedWebhook(Request $request): array
    {
        $raw = $request->getContent();
        $timestamp = (string) $request->header('Revolut-Request-Timestamp');
        $signatures = explode(',', (string) $request->header('Revolut-Signature'));
        $timestampSeconds = strlen($timestamp) > 10 ? intdiv((int) $timestamp, 1000) : (int) $timestamp;
        $expected = 'v1='.hash_hmac('sha256', 'v1.'.$timestamp.'.'.$raw, (string) config('services.payments.revolut.webhook_secret'));
        $valid = $timestampSeconds > 0 && abs(time() - $timestampSeconds) <= 300
            && collect($signatures)->contains(fn (string $signature): bool => hash_equals($expected, trim($signature)));
        abort_unless($valid, 400);
        $event = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $orderId = (string) ($event['order_id'] ?? '');
        $data = $this->client()->get('/api/orders/'.$orderId)->throw()->json();
        $confirmation = $this->confirmation(null, is_array($data) ? $data : []);

        return [
            'event_id' => hash('sha256', $timestamp.'.'.$raw),
            'event_type' => (string) ($event['event'] ?? ''),
            ...$confirmation,
            'paid' => ($event['event'] ?? null) === 'ORDER_COMPLETED' && $confirmation['paid'],
            'payload' => $event,
        ];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.payments.revolut.base_url'))
            ->withToken((string) config('services.payments.revolut.secret'))
            ->withHeaders(['Revolut-Api-Version' => (string) config('services.payments.revolut.api_version')])
            ->acceptJson()->connectTimeout(5)->timeout(15);
    }

    /** @return array<string, mixed> */
    private function confirmation(?CreditPayment $payment, array $data): array
    {
        $publicId = (string) ($data['metadata']['credit_payment_id'] ?? $data['merchant_order_ext_ref'] ?? '');

        return [
            'payment_public_id' => $publicId,
            'provider_order_id' => (string) ($data['id'] ?? ''),
            'provider_transaction_id' => (string) ($data['payments'][0]['id'] ?? ''),
            'amount_minor' => (int) ($data['amount'] ?? -1),
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'paid' => ($data['state'] ?? null) === 'completed'
                && ($payment === null || ($publicId === $payment->public_id && (int) ($data['amount'] ?? -1) === $payment->amount_minor && strtoupper((string) ($data['currency'] ?? '')) === $payment->currency)),
            'refunded' => ($data['state'] ?? null) === 'refunded',
            'payload' => $data,
        ];
    }
}
