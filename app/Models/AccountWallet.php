<?php

namespace App\Models;

use App\Economy\WalletCurrency;
use Database\Factories\AccountWalletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stores the current application-owned balance for one game account and currency.
 */
#[Fillable(['account_id', 'currency', 'balance'])]
final class AccountWallet extends Model
{
    /** @use HasFactory<AccountWalletFactory> */
    use HasFactory;

    /**
     * Returns the immutable ledger entries that produced the current balance.
     *
     * @return HasMany<WalletTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /**
     * Casts balance and currency to their exact domain types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => WalletCurrency::class,
            'balance' => 'integer',
        ];
    }
}
