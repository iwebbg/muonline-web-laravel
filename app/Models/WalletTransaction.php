<?php

namespace App\Models;

use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use Database\Factories\WalletTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records one immutable wallet balance transition with its idempotency fingerprint.
 */
#[Fillable([
    'idempotency_key',
    'request_hash',
    'account_wallet_id',
    'account_id',
    'currency',
    'type',
    'amount',
    'balance_before',
    'balance_after',
    'reason',
    'metadata',
    'performed_by_administrator_id',
])]
final class WalletTransaction extends Model
{
    /** @use HasFactory<WalletTransactionFactory> */
    use HasFactory;

    /**
     * Returns the wallet whose state this ledger entry changed.
     *
     * @return BelongsTo<AccountWallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(AccountWallet::class, 'account_wallet_id');
    }

    /**
     * Casts ledger values without losing integer precision in the PHP runtime.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => WalletCurrency::class,
            'type' => WalletTransactionType::class,
            'amount' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
            'metadata' => 'array',
        ];
    }
}
