<?php

namespace App\Payments\Actions;

use App\Models\Administrator;
use App\Models\CreditPayment;
use App\Models\ManualPaymentReceipt;
use App\Payments\CreditPaymentStatus;
use App\Payments\PaymentProvider;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Atomically records manual receipt and credits the snapshotted package once. */
final readonly class ConfirmRevolutTagPayment
{
    /** Reuses the common verified-payment wallet boundary. */
    public function __construct(private CompleteCreditPayment $complete) {}

    /**
     * Locks the purchase and commits its reviewer, receipt and wallet entry together.
     *
     * @throws ValidationException When the transfer was already used elsewhere.
     */
    public function execute(CreditPayment $payment, Administrator $administrator, string $reference): CreditPayment
    {
        try {
            return DB::transaction(function () use ($payment, $administrator, $reference): CreditPayment {
                $locked = CreditPayment::query()->lockForUpdate()->findOrFail($payment->getKey());

                abort_unless($locked->provider === PaymentProvider::RevolutTag, 404);

                if ($locked->credited_at !== null) {
                    return $locked;
                }

                abort_unless($locked->status === CreditPaymentStatus::Pending, 409);

                ManualPaymentReceipt::query()->create([
                    'credit_payment_id' => $locked->getKey(),
                    'administrator_id' => $administrator->getKey(),
                    'reference' => strtolower($reference),
                    'snapshot' => [
                        'account_id' => $locked->account_id,
                        'amount_minor' => $locked->amount_minor,
                        'currency' => $locked->currency,
                        'credits' => $locked->credits,
                        'recipient_tag' => $locked->provider_payload['recipient_tag'] ?? null,
                    ],
                ]);

                return $this->complete->execute($locked, [
                    'paid' => true,
                    'amount_minor' => $locked->amount_minor,
                    'currency' => $locked->currency,
                    'provider_transaction_id' => strtolower($reference),
                    'payload' => [
                        ...($locked->provider_payload ?? []),
                        'reviewed_by_administrator_id' => $administrator->getKey(),
                    ],
                ], administratorId: (int) $administrator->getKey());
            }, attempts: 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'reference' => __('ui.revolut_tag.reference_used'),
            ]);
        }
    }
}
