<?php

namespace Tests\Feature;

use App\Economy\WalletTransactionType;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Models\CreditPayment;
use App\Models\PaymentWebhookEvent;
use App\Models\WalletTransaction;
use App\Payments\CreditPaymentStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/** Verifies signed Stripe webhooks and exactly-once Credits settlement. */
final class PaymentWebhookTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_stripe_event_credits_wallet_once_across_replays(): void
    {
        config()->set('services.payments.stripe', ['enabled' => true, 'secret' => 'sk_test', 'webhook_secret' => 'whsec_test', 'base_url' => 'https://api.stripe.test']);
        $payment = CreditPayment::factory()->create(['account_id' => 'payer01', 'provider_order_id' => 'cs_paid', 'credits' => 500, 'amount_minor' => 500]);
        $lookup = $this->mock(GameAccountLookupRepository::class);
        $lookup->shouldReceive('exists')->once()->with('payer01')->andReturnTrue();
        $payload = json_encode([
            'id' => 'evt_paid', 'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_paid', 'client_reference_id' => $payment->public_id, 'metadata' => ['credit_payment_id' => $payment->public_id], 'payment_intent' => 'pi_paid', 'payment_status' => 'paid', 'amount_total' => 500, 'currency' => 'eur']],
        ], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        foreach ([1, 2] as $attempt) {
            $this->call('POST', route('payments.webhooks', 'stripe'), [], [], [], [
                'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
                'CONTENT_TYPE' => 'application/json',
            ], $payload)->assertNoContent();
        }

        $this->assertSame(1, WalletTransaction::query()->count());
        $this->assertSame(WalletTransactionType::Payment, WalletTransaction::query()->sole()->type);
        $this->assertSame(CreditPaymentStatus::Paid, $payment->refresh()->status);
        $this->assertSame(1, PaymentWebhookEvent::query()->count());
    }

    public function test_invalid_stripe_signature_is_rejected_without_state_change(): void
    {
        config()->set('services.payments.stripe', ['enabled' => true, 'secret' => 'sk_test', 'webhook_secret' => 'whsec_test', 'base_url' => 'https://api.stripe.test']);

        $this->withHeader('Stripe-Signature', 't='.time().',v1=invalid')->postJson(route('payments.webhooks', 'stripe'), ['id' => 'evt_invalid'])->assertBadRequest();

        $this->assertSame(0, PaymentWebhookEvent::query()->count());
        $this->assertSame(0, WalletTransaction::query()->count());
    }
}
