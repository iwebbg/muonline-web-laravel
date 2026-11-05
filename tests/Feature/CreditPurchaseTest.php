<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Models\CreditPackage;
use App\Models\CreditPayment;
use App\Payments\PaymentProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Verifies package visibility and hosted checkout creation. */
final class CreditPurchaseTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_account_starts_stripe_checkout_from_published_package(): void
    {
        config()->set('services.payments.stripe', ['enabled' => true, 'secret' => 'sk_test', 'webhook_secret' => 'whsec_test', 'base_url' => 'https://api.stripe.test']);
        $package = CreditPackage::factory()->create(['credits' => 1200, 'price_minor' => 999]);
        Http::preventStrayRequests();
        Http::fake(['https://api.stripe.test/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.test/session'])]);
        $csrfToken = 'credit-purchase-token';

        $this->actingAs($this->account('buyer01'))->withSession(['_token' => $csrfToken])->post(route('account.credit-packages.purchase', $package), [
            '_token' => $csrfToken,
            'provider' => PaymentProvider::Stripe->value,
        ])->assertRedirect('https://checkout.stripe.test/session');

        $payment = CreditPayment::query()->sole();
        $this->assertSame('buyer01', $payment->account_id);
        $this->assertSame(1200, $payment->credits);
        $this->assertSame(999, $payment->amount_minor);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key'));
    }

    public function test_authenticated_account_starts_paypal_checkout(): void
    {
        config()->set('services.payments.paypal', ['enabled' => true, 'client_id' => 'client', 'secret' => 'secret', 'webhook_id' => 'webhook', 'base_url' => 'https://api.paypal.test']);
        $package = CreditPackage::factory()->create();
        Http::preventStrayRequests();
        Http::fake([
            'https://api.paypal.test/v1/oauth2/token' => Http::response(['access_token' => 'access']),
            'https://api.paypal.test/v2/checkout/orders' => Http::response(['id' => 'order-1', 'links' => [['rel' => 'payer-action', 'href' => 'https://paypal.test/approve']]]),
        ]);
        $csrfToken = 'paypal-purchase-token';

        $this->actingAs($this->account('buyer02'))->withSession(['_token' => $csrfToken])->post(route('account.credit-packages.purchase', $package), [
            '_token' => $csrfToken, 'provider' => PaymentProvider::Paypal->value,
        ])->assertRedirect('https://paypal.test/approve');

        $this->assertSame('order-1', CreditPayment::query()->sole()->provider_order_id);
    }

    public function test_authenticated_account_starts_revolut_checkout(): void
    {
        config()->set('services.payments.revolut', ['enabled' => true, 'secret' => 'secret', 'webhook_secret' => 'webhook', 'api_version' => '2026-08-17', 'base_url' => 'https://api.revolut.test']);
        $package = CreditPackage::factory()->create();
        Http::preventStrayRequests();
        Http::fake(['https://api.revolut.test/api/orders' => Http::response(['id' => 'order-2', 'checkout_url' => 'https://revolut.test/checkout'])]);
        $csrfToken = 'revolut-purchase-token';

        $this->actingAs($this->account('buyer03'))->withSession(['_token' => $csrfToken])->post(route('account.credit-packages.purchase', $package), [
            '_token' => $csrfToken, 'provider' => PaymentProvider::Revolut->value,
        ])->assertRedirect('https://revolut.test/checkout');

        $this->assertSame('order-2', CreditPayment::query()->sole()->provider_order_id);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Revolut-Api-Version', '2026-08-17'));
    }

    private function account(string $username): GameAccount
    {
        return new GameAccount(1, $username, 'Buyer', 'buyer@example.com', false, false, 'secret1');
    }
}
