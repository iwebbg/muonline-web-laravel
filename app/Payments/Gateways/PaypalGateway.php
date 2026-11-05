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

/** Implements PayPal Orders v2 and signed webhook postback verification. */
final class PaypalGateway implements PaymentGateway
{
    public function provider(): PaymentProvider
    {
        return PaymentProvider::Paypal;
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.payments.paypal.enabled')
            && filled(config('services.payments.paypal.client_id'))
            && filled(config('services.payments.paypal.secret'))
            && filled(config('services.payments.paypal.webhook_id'));
    }

    public function createCheckout(CreditPayment $payment): CheckoutSession
    {
        $data = $this->client()->withHeaders(['PayPal-Request-Id' => $payment->idempotency_key])->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $payment->public_id,
                'custom_id' => $payment->public_id,
                'description' => $payment->package_snapshot['title_en'],
                'amount' => ['currency_code' => $payment->currency, 'value' => number_format($payment->amount_minor / 100, 2, '.', '')],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'return_url' => route('account.credit-payments.return', [$payment, 'provider' => 'paypal']),
                'cancel_url' => route('account.credits.index', ['payment' => 'cancelled']),
                'user_action' => 'PAY_NOW',
            ]]],
        ])->throw()->json();
        $approve = collect($data['links'] ?? [])->firstWhere('rel', 'payer-action')
            ?? collect($data['links'] ?? [])->firstWhere('rel', 'approve');

        if (! isset($data['id']) || ! is_array($approve) || ! isset($approve['href'])) {
            throw new RuntimeException('PayPal did not return an approval URL.');
        }

        return new CheckoutSession((string) $data['id'], (string) $approve['href']);
    }

    public function confirmReturn(CreditPayment $payment, Request $request): array
    {
        $orderId = $request->string('token')->toString();
        abort_unless($orderId !== '' && hash_equals((string) $payment->provider_order_id, $orderId), 404);
        $data = $this->client()->withHeaders(['PayPal-Request-Id' => 'capture-'.$payment->idempotency_key])->withBody('{}', 'application/json')->post('/v2/checkout/orders/'.$orderId.'/capture')->throw()->json();

        return $this->confirmation($payment, is_array($data) ? $data : []);
    }

    public function verifiedWebhook(Request $request): array
    {
        $event = $request->json()->all();
        $verification = $this->client()->post('/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url' => $request->header('PAYPAL-CERT-URL'),
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            'webhook_id' => config('services.payments.paypal.webhook_id'),
            'webhook_event' => $event,
        ])->throw()->json();
        abort_unless(($verification['verification_status'] ?? null) === 'SUCCESS', 400);
        $resource = $event['resource'] ?? [];
        $orderId = (string) ($resource['supplementary_data']['related_ids']['order_id'] ?? '');

        return [
            'event_id' => (string) ($event['id'] ?? hash('sha256', $request->getContent())),
            'event_type' => (string) ($event['event_type'] ?? ''),
            'payment_public_id' => '',
            'provider_order_id' => $orderId,
            'provider_transaction_id' => (string) ($resource['id'] ?? ''),
            'amount_minor' => (int) round(((float) ($resource['amount']['value'] ?? -1)) * 100),
            'currency' => strtoupper((string) ($resource['amount']['currency_code'] ?? '')),
            'paid' => ($event['event_type'] ?? null) === 'PAYMENT.CAPTURE.COMPLETED' && ($resource['status'] ?? null) === 'COMPLETED',
            'refunded' => in_array(($event['event_type'] ?? null), ['PAYMENT.CAPTURE.REFUNDED', 'PAYMENT.CAPTURE.REVERSED'], true),
            'payload' => $event,
        ];
    }

    private function client(): PendingRequest
    {
        $token = Http::baseUrl((string) config('services.payments.paypal.base_url'))
            ->withBasicAuth((string) config('services.payments.paypal.client_id'), (string) config('services.payments.paypal.secret'))
            ->asForm()->connectTimeout(5)->timeout(15)->post('/v1/oauth2/token', ['grant_type' => 'client_credentials'])->throw()->json('access_token');

        return Http::baseUrl((string) config('services.payments.paypal.base_url'))->withToken((string) $token)->acceptJson()->connectTimeout(5)->timeout(15);
    }

    /** @return array<string, mixed> */
    private function confirmation(CreditPayment $payment, array $data): array
    {
        $unit = $data['purchase_units'][0] ?? [];
        $capture = $unit['payments']['captures'][0] ?? [];
        $amount = $capture['amount'] ?? $unit['amount'] ?? [];
        $publicId = (string) ($unit['custom_id'] ?? $unit['reference_id'] ?? '');

        return [
            'payment_public_id' => $publicId,
            'provider_order_id' => (string) ($data['id'] ?? ''),
            'provider_transaction_id' => (string) ($capture['id'] ?? ''),
            'amount_minor' => (int) round(((float) ($amount['value'] ?? -1)) * 100),
            'currency' => strtoupper((string) ($amount['currency_code'] ?? '')),
            'paid' => ($data['status'] ?? null) === 'COMPLETED' && $publicId === $payment->public_id
                && (int) round(((float) ($amount['value'] ?? -1)) * 100) === $payment->amount_minor
                && strtoupper((string) ($amount['currency_code'] ?? '')) === $payment->currency,
            'refunded' => false,
            'payload' => $data,
        ];
    }
}
