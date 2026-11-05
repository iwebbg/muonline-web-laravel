<?php

namespace App\Models;

use App\Database\Casts\LowercaseUuid;
use App\Payments\CreditPaymentStatus;
use App\Payments\PaymentProvider;
use Database\Factories\CreditPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Stores one immutable package snapshot and its provider lifecycle. */
#[Fillable(['public_id', 'idempotency_key', 'account_id', 'credit_package_id', 'package_snapshot', 'provider', 'status', 'credits', 'amount_minor', 'currency', 'provider_order_id', 'provider_transaction_id', 'provider_payload', 'wallet_transaction_id', 'paid_at', 'credited_at', 'failed_at', 'refunded_at'])]
final class CreditPayment extends Model
{
    /** @use HasFactory<CreditPaymentFactory> */
    use HasFactory;

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<CreditPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(CreditPackage::class, 'credit_package_id');
    }

    /** @return BelongsTo<WalletTransaction, $this> */
    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'public_id' => LowercaseUuid::class,
            'package_snapshot' => 'array',
            'provider_payload' => 'array',
            'provider' => PaymentProvider::class,
            'status' => CreditPaymentStatus::class,
            'credits' => 'integer',
            'amount_minor' => 'integer',
            'paid_at' => 'datetime',
            'credited_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }
}
