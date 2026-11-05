<?php

namespace App\Console\Commands;

use App\Payments\PaymentProvider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

#[Signature('payments:webhook:register {provider : Payment provider: stripe or revolut} {--url= : Public HTTPS webhook URL; defaults to the application webhook route}')]
/**
 * Registers a signed provider webhook and returns its one-time signing secret.
 */
#[Description('Register a Stripe or Revolut payment webhook and print its signing secret.')]
final class RegisterPaymentWebhook extends Command
{
    /** @var list<string> */
    private const STRIPE_EVENTS = [
        'checkout.session.completed',
        'charge.refunded',
    ];

    /** @var list<string> */
    private const REVOLUT_EVENTS = [
        'ORDER_COMPLETED',
    ];

    /**
     * Creates the selected provider registration without persisting its secret locally.
     */
    public function handle(): int
    {
        $provider = PaymentProvider::tryFrom(strtolower((string) $this->argument('provider')));

        if (! in_array($provider, [PaymentProvider::Stripe, PaymentProvider::Revolut], true)) {
            $this->components->error('Provider must be either stripe or revolut.');

            return self::FAILURE;
        }

        $webhookUrl = $this->webhookUrl($provider);

        if ($webhookUrl === null) {
            return self::FAILURE;
        }

        try {
            $response = match ($provider) {
                PaymentProvider::Stripe => $this->registerStripeWebhook($webhookUrl),
                PaymentProvider::Revolut => $this->registerRevolutWebhook($webhookUrl),
                default => null,
            };
        } catch (ConnectionException) {
            $this->components->error('The payment provider could not be reached. No webhook secret was created.');

            return self::FAILURE;
        }

        if (! $response instanceof Response) {
            return self::FAILURE;
        }

        return $this->displayRegistration($provider, $webhookUrl, $response);
    }

    /**
     * Resolves and validates the public endpoint registered with the provider.
     */
    private function webhookUrl(PaymentProvider $provider): ?string
    {
        $requestedUrl = trim((string) $this->option('url'));
        $webhookUrl = $requestedUrl !== ''
            ? $requestedUrl
            : route('payments.webhooks', ['provider' => $provider->value]);

        if (filter_var($webhookUrl, FILTER_VALIDATE_URL) === false || parse_url($webhookUrl, PHP_URL_SCHEME) !== 'https') {
            $this->components->error('The webhook URL must be a valid public HTTPS URL.');

            return null;
        }

        return $webhookUrl;
    }

    /**
     * Creates a Stripe webhook endpoint for the events handled by this application.
     */
    private function registerStripeWebhook(string $webhookUrl): ?Response
    {
        $secretKey = trim((string) config('services.payments.stripe.secret'));

        if ($secretKey === '') {
            $this->components->error('STRIPE_SECRET_KEY must be configured before registering the webhook.');

            return null;
        }

        return $this->providerClient('services.payments.stripe', $secretKey)
            ->asForm()
            ->post('/v1/webhook_endpoints', [
                'url' => $webhookUrl,
                'enabled_events' => self::STRIPE_EVENTS,
                'description' => 'MuOnline Web Credits payments',
            ]);
    }

    /**
     * Creates a Revolut Merchant webhook for completed hosted-checkout orders.
     */
    private function registerRevolutWebhook(string $webhookUrl): ?Response
    {
        $secretKey = trim((string) config('services.payments.revolut.secret'));

        if ($secretKey === '') {
            $this->components->error('REVOLUT_SECRET_KEY must be configured before registering the webhook.');

            return null;
        }

        return $this->providerClient('services.payments.revolut', $secretKey)
            ->withHeaders([
                'Revolut-Api-Version' => (string) config('services.payments.revolut.api_version'),
            ])
            ->post('/api/webhooks', [
                'url' => $webhookUrl,
                'events' => self::REVOLUT_EVENTS,
            ]);
    }

    /**
     * Builds a short-lived, bounded HTTP client for one provider API.
     */
    private function providerClient(string $configurationKey, string $secretKey): PendingRequest
    {
        return Http::baseUrl((string) config($configurationKey.'.base_url'))
            ->withToken($secretKey)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(15);
    }

    /**
     * Prints the environment value that must be copied before leaving the terminal.
     */
    private function displayRegistration(PaymentProvider $provider, string $webhookUrl, Response $response): int
    {
        if (! $response->successful()) {
            $this->components->error("{$provider->name} rejected the webhook registration with HTTP {$response->status()}.");

            return self::FAILURE;
        }

        $secretField = $provider === PaymentProvider::Stripe ? 'secret' : 'signing_secret';
        $environmentVariable = $provider === PaymentProvider::Stripe ? 'STRIPE_WEBHOOK_SECRET' : 'REVOLUT_WEBHOOK_SECRET';
        $signingSecret = trim((string) $response->json($secretField));
        $endpointId = trim((string) $response->json('id'));

        if ($signingSecret === '') {
            $this->components->error("{$provider->name} created the endpoint but did not return a signing secret.");

            return self::FAILURE;
        }

        $this->components->info("{$provider->name} webhook registered successfully.");
        $this->components->twoColumnDetail('Webhook URL', $webhookUrl);

        if ($endpointId !== '') {
            $this->components->twoColumnDetail('Endpoint ID', $endpointId);
        }

        $this->components->warn('Copy this value to .env now. The application does not store it automatically:');
        $this->line("{$environmentVariable}={$signingSecret}");

        return self::SUCCESS;
    }
}
