<?php

namespace App\Payments\Gateways;

use App\Models\CreditPayment;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Data\CheckoutSession;
use App\Payments\PaymentProvider;
use Illuminate\Http\Request;

/** Creates manual transfer instructions without accepting browser payment claims. */
final class RevolutTagGateway implements PaymentGateway
{
    /** Identifies the manual provider independently of Revolut Merchant checkout. */
    public function provider(): PaymentProvider
    {
        return PaymentProvider::RevolutTag;
    }

    /** Requires an explicit opt-in and a usable recipient tag. */
    public function isEnabled(): bool
    {
        $tag = (string) config('services.payments.revolut_tag.tag', '');

        return (bool) config('services.payments.revolut_tag.enabled', false)
            && preg_match('/\A@?[a-zA-Z0-9_.-]{3,50}\z/', $tag) === 1;
    }

    /** Snapshots the recipient so later configuration changes cannot redirect a purchase. */
    public function createCheckout(CreditPayment $payment): CheckoutSession
    {
        return new CheckoutSession(
            providerOrderId: $payment->public_id,
            checkoutUrl: route('account.revolut-tag.show', $payment),
            providerPayload: [
                'recipient_tag' => '@'.ltrim((string) config('services.payments.revolut_tag.tag'), '@'),
                'reference' => $payment->account_id,
            ],
        );
    }

    /**
     * Opens Revolut with the saved recipient, minor-unit amount and account note.
     *
     * Revolut's current web form reads these query parameters. Mobile app handoff
     * may omit the note, so the instructions still require checking it manually.
     * Returns null for older or incomplete purchases without a usable recipient.
     */
    public function paymentUrl(CreditPayment $payment): ?string
    {
        $recipientTag = ltrim((string) ($payment->provider_payload['recipient_tag'] ?? ''), '@');

        if (preg_match('/\A[a-zA-Z0-9_.-]{3,50}\z/', $recipientTag) !== 1) {
            return null;
        }

        $parameters = http_build_query([
            'currency' => $payment->currency,
            'amount' => $payment->amount_minor,
            'note' => $payment->account_id,
        ], encoding_type: PHP_QUERY_RFC3986);

        return 'https://revolut.me/'.rawurlencode($recipientTag).'?'.$parameters;
    }

    /**
     * Keeps manual transfers pending until an economy administrator verifies receipt.
     *
     * @return array{paid: false}
     */
    public function confirmReturn(CreditPayment $payment, Request $request): array
    {
        return ['paid' => false];
    }

    /**
     * Rejects webhook attempts because this provider has no automated confirmation.
     *
     * @return array<string, mixed>
     */
    public function verifiedWebhook(Request $request): array
    {
        abort(404);
    }
}
