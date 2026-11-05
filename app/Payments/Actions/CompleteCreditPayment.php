<?php

namespace App\Payments\Actions;

use App\Economy\Actions\AdjustWalletBalance;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Models\CreditPayment;
use App\Payments\CreditPaymentStatus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Reconciles a verified provider result and credits the wallet exactly once. */
final readonly class CompleteCreditPayment
{
    public function __construct(private AdjustWalletBalance $adjustWalletBalance) {}

    /** @param array<string, mixed> $confirmation */
    public function execute(CreditPayment $payment, array $confirmation): CreditPayment
    {
        return DB::transaction(function () use ($payment, $confirmation): CreditPayment {
            $locked = CreditPayment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if (($confirmation['refunded'] ?? false) === true) {
                $locked->update(['status' => CreditPaymentStatus::Refunded, 'refunded_at' => now(), 'provider_payload' => $confirmation['payload'] ?? null]);

                return $locked->refresh();
            }
            if ($locked->credited_at !== null) {
                return $locked;
            }
            if (($confirmation['paid'] ?? false) !== true
                || (int) ($confirmation['amount_minor'] ?? -1) !== $locked->amount_minor
                || strtoupper((string) ($confirmation['currency'] ?? '')) !== $locked->currency) {
                throw new RuntimeException('Provider confirmation does not match the payment snapshot.');
            }
            $transaction = $this->adjustWalletBalance->execute(
                accountId: $locked->account_id,
                currency: WalletCurrency::Credits,
                type: WalletTransactionType::Payment,
                amount: $locked->credits,
                reason: 'Credits package payment · '.$locked->provider->value,
                administratorId: null,
                idempotencyKey: $locked->idempotency_key,
            );
            $locked->update([
                'status' => CreditPaymentStatus::Paid,
                'provider_transaction_id' => $confirmation['provider_transaction_id'] ?: null,
                'provider_payload' => $confirmation['payload'] ?? null,
                'wallet_transaction_id' => $transaction->getKey(),
                'paid_at' => now(),
                'credited_at' => now(),
            ]);

            return $locked->refresh();
        }, attempts: 3);
    }
}
