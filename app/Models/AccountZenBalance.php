<?php

namespace App\Models;

use Database\Factories\AccountZenBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stores one account-scoped web Zen balance backed by audited transfers.
 */
#[Fillable(['account_id', 'balance'])]
final class AccountZenBalance extends Model
{
    /** @use HasFactory<AccountZenBalanceFactory> */
    use HasFactory;

    /**
     * Returns every transfer that changed or reserved this balance.
     *
     * @return HasMany<ZenBankOperation, $this>
     */
    public function operations(): HasMany
    {
        return $this->hasMany(ZenBankOperation::class);
    }

    /**
     * Casts the balance to a stable integer value.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['balance' => 'integer'];
    }
}
