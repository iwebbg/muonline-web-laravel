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

/** Implements Stripe Checkout without requiring a provider SDK. */
final class StripeGateway implements PaymentGateway
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::Stripe;
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.payments.stripe.enabled')
            && filled(config('services.payments.stripe.secret'))
            && filled(config('services.payments.stripe.webhook_secret'));
    }

    public function createCheckout(CreditPayment $payment): CheckoutSession
    {
        $response = $this->client()->asForm()->withHeaders(['Idempotency-Key' => $payment->idempotency_key])->post('/v1/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => route('account.credit-payments.return', [$payment, 'provider' => 'stripe']).'&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('account.credits.index', ['payment' => 'cancelled']),
            'client_reference_id' => $payment->public_id,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => ['currency' => strtolower($payment->currency), 'unit_amount' => $payment->amount_minor, 'product_data' => ['name' => $payment->package_snapshot['title_en']]],
            ]],
            'metadata' => ['credit_payment_id' => $payment->public_id],
            'payment_intent_data' => ['metadata' => ['credit_payment_id' => $payment->public_id]],
        ])->throw()->json();

        if (! is_array($response) || ! isset($response['id'], $response['url'])) {
            throw new RuntimeException('Stripe did not return a checkout session.');
        }

        return new CheckoutSession((string) $response['id'], (string) $response['url']);
    }

    public function confirmReturn(CreditPayment $payment, Request $request): array
    {
        $sessionId = $request->string('session_id')->toString();
        $data = $this->client()->get('/v1/checkout/sessions/'.$sessionId)->throw()->json();

        return $this->confirmation($payment, is_array($data) ? $data : []);
    }

    public function verifiedWebhook(Request $request): array
    {
        $raw = $request->getContent();
        $header = (string) $request->header('Stripe-Signature');
        preg_match('/(?:^|,)t=(\d+)/', $header, $timestampMatch);
        preg_match_all('/(?:^|,)v1=([a-f0-9]+)/', $header, $signatureMatches);
        $timestamp = (int) ($timestampMatch[1] ?? 0);
        $expected = hash_hmac('sha256', $timestamp.'.'.$raw, (string) config('services.payments.stripe.webhook_secret'));
        $valid = $timestamp > 0 && abs(time() - $timestamp) <= 300
            && collect($signatureMatches[1] ?? [])->contains(fn (string $signature): bool => hash_equals($expected, $signature));

        abort_unless($valid, 400);
        $event = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $object = $event['data']['object'] ?? [];
        $confirmation = $this->confirmation(null, is_array($object) ? $object : []);
        $eventType = (string) ($event['type'] ?? '');

        return [
            'event_id' => (string) ($event['id'] ?? hash('sha256', $raw)),
            'event_type' => $eventType,
            ...$confirmation,
            'paid' => $eventType === 'checkout.session.completed' && $confirmation['paid'],
            'refunded' => $eventType === 'charge.refunded' && (int) ($object['amount_refunded'] ?? 0) >= (int) ($object['amount'] ?? 1),
            'amount_minor' => $eventType === 'charge.refunded' ? (int) ($object['amount_refunded'] ?? -1) : $confirmation['amount_minor'],
            'payload' => $event,
        ];
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.payments.stripe.base_url'))
            ->withToken((string) config('services.payments.stripe.secret'))
            ->acceptJson()->connectTimeout(5)->timeout(15);
    }

    /** @return array<string, mixed> */
    private function confirmation(?CreditPayment $payment, array $data): array
    {
        $publicId = (string) ($data['metadata']['credit_payment_id'] ?? $data['client_reference_id'] ?? '');

        return [
            'payment_public_id' => $publicId,
            'provider_order_id' => (string) ($data['id'] ?? ''),
            'provider_transaction_id' => (string) ($data['payment_intent'] ?? ''),
            'amount_minor' => (int) ($data['amount_total'] ?? $data['amount'] ?? -1),
            'currency' => strtoupper((string) ($data['currency'] ?? '')),
            'paid' => ($data['payment_status'] ?? null) === 'paid'
                && ($payment === null || ($publicId === $payment->public_id && (int) ($data['amount_total'] ?? -1) === $payment->amount_minor && strtoupper((string) ($data['currency'] ?? '')) === $payment->currency)),
            'refunded' => false,
            'payload' => $data,
        ];
    }
}
