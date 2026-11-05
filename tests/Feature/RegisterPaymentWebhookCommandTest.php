<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verifies provider webhook registration and one-time signing-secret output.
 */
final class RegisterPaymentWebhookCommandTest extends TestCase
{
    /**
     * Prevents an incomplete fake from reaching a real payment provider.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    /**
     * Registers the exact Stripe events consumed by the payment webhook handler.
     */
    public function test_it_registers_a_stripe_webhook_and_prints_the_signing_secret(): void
    {
        config()->set('services.payments.stripe', [
            'secret' => 'sk_test_secret',
            'base_url' => 'https://api.stripe.test',
        ]);
        Http::fake([
            'https://api.stripe.test/v1/webhook_endpoints' => Http::response([
                'id' => 'we_test_123',
                'secret' => 'whsec_test_123',
            ], 200),
        ]);

        $this->artisan('payments:webhook:register', [
            'provider' => 'stripe',
            '--url' => 'https://mu.example.com/payments/webhooks/stripe',
        ])
            ->expectsOutputToContain('Stripe webhook registered successfully')
            ->expectsOutputToContain('STRIPE_WEBHOOK_SECRET=whsec_test_123')
            ->assertSuccessful();

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.stripe.test/v1/webhook_endpoints'
                && $request->hasHeader('Authorization', 'Bearer sk_test_secret')
                && $request['url'] === 'https://mu.example.com/payments/webhooks/stripe'
                && $request['enabled_events'] === ['checkout.session.completed', 'charge.refunded'];
        });
    }

    /**
     * Registers the Revolut completion event with its pinned API version.
     */
    public function test_it_registers_a_revolut_webhook_and_prints_the_signing_secret(): void
    {
        config()->set('services.payments.revolut', [
            'secret' => 'sk_revolut_secret',
            'api_version' => '2026-08-17',
            'base_url' => 'https://api.revolut.test',
        ]);
        Http::fake([
            'https://api.revolut.test/api/webhooks' => Http::response([
                'id' => 'hook_test_123',
                'signing_secret' => 'wsk_test_123',
            ], 201),
        ]);

        $this->artisan('payments:webhook:register', [
            'provider' => 'revolut',
            '--url' => 'https://mu.example.com/payments/webhooks/revolut',
        ])
            ->expectsOutputToContain('Revolut webhook registered successfully')
            ->expectsOutputToContain('REVOLUT_WEBHOOK_SECRET=wsk_test_123')
            ->assertSuccessful();

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.revolut.test/api/webhooks'
                && $request->hasHeader('Authorization', 'Bearer sk_revolut_secret')
                && $request->hasHeader('Revolut-Api-Version', '2026-08-17')
                && $request['url'] === 'https://mu.example.com/payments/webhooks/revolut'
                && $request['events'] === ['ORDER_COMPLETED'];
        });
    }

    /**
     * Rejects unsupported providers before making an external request.
     */
    public function test_it_rejects_a_provider_without_registration_support(): void
    {
        $this->artisan('payments:webhook:register', ['provider' => 'paypal'])
            ->expectsOutputToContain('Provider must be either stripe or revolut')
            ->assertFailed();

        Http::assertNothingSent();
    }

    /**
     * Rejects endpoints that payment providers cannot call securely.
     */
    public function test_it_requires_a_public_https_webhook_url(): void
    {
        config()->set('services.payments.stripe.secret', 'sk_test_secret');

        $this->artisan('payments:webhook:register', [
            'provider' => 'stripe',
            '--url' => 'http://localhost/payments/webhooks/stripe',
        ])
            ->expectsOutputToContain('webhook URL must be a valid public HTTPS URL')
            ->assertFailed();

        Http::assertNothingSent();
    }

    /**
     * Fails safely when the provider API does not return a signing secret.
     */
    public function test_it_fails_when_the_provider_omits_the_signing_secret(): void
    {
        config()->set('services.payments.revolut', [
            'secret' => 'sk_revolut_secret',
            'api_version' => '2026-08-17',
            'base_url' => 'https://api.revolut.test',
        ]);
        Http::fake([
            'https://api.revolut.test/api/webhooks' => Http::response(['id' => 'hook_test_123'], 201),
        ]);

        $this->artisan('payments:webhook:register', [
            'provider' => 'revolut',
            '--url' => 'https://mu.example.com/payments/webhooks/revolut',
        ])
            ->expectsOutputToContain('did not return a signing secret')
            ->assertFailed();
    }
}
