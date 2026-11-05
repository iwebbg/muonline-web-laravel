<?php

namespace App\Http\Controllers;

use App\Models\CreditPayment;
use App\Models\PaymentWebhookEvent;
use App\Payments\Actions\CompleteCreditPayment;
use App\Payments\PaymentGatewayManager;
use App\Payments\PaymentProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/** Verifies, records and idempotently processes provider payment events. */
final class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentProvider $provider, PaymentGatewayManager $gateways, CompleteCreditPayment $complete): Response
    {
        abort_unless($gateways->for($provider)->isEnabled(), 404);
        $confirmation = $gateways->for($provider)->verifiedWebhook($request);
        $event = PaymentWebhookEvent::query()->firstOrCreate(
            ['provider' => $provider, 'provider_event_id' => $confirmation['event_id']],
            ['payload_hash' => hash('sha256', $request->getContent()), 'event_type' => $confirmation['event_type'], 'status' => 'received', 'payload' => $confirmation['payload']],
        );

        if ($event->processed_at !== null) {
            return response()->noContent();
        }
        $payment = CreditPayment::query()
            ->when($confirmation['payment_public_id'] !== '', fn ($query) => $query->where('public_id', $confirmation['payment_public_id']))
            ->when($confirmation['payment_public_id'] === '', fn ($query) => $query->where(function ($providerQuery) use ($confirmation): void {
                $providerQuery->where('provider_order_id', $confirmation['provider_order_id']);

                if ($confirmation['provider_transaction_id'] !== '') {
                    $providerQuery->orWhere('provider_transaction_id', $confirmation['provider_transaction_id']);
                }
            }))
            ->where('provider', $provider)
            ->first();

        if (! $payment instanceof CreditPayment) {
            $event->update(['status' => 'ignored', 'failure_reason' => 'Payment not found.', 'processed_at' => now()]);

            return response()->noContent();
        }

        try {
            if (($confirmation['paid'] ?? false) || ($confirmation['refunded'] ?? false)) {
                $complete->execute($payment, $confirmation);
            }
            $event->update(['status' => 'processed', 'processed_at' => now()]);
        } catch (Throwable $exception) {
            report($exception);
            $event->update(['status' => 'failed', 'failure_reason' => $exception->getMessage()]);

            return response('Webhook processing failed.', 500);
        }

        return response()->noContent();
    }
}
