<?php

namespace App\Models;

use App\Economy\NativePurchaseKind;
use App\Game\Operations\CharacterOperationStatus;
use Database\Factories\NativeAccountPurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Retains a native coin or account VIP purchase across wallet, game and response failures.
 */
#[Fillable([
    'idempotency_key', 'request_hash', 'game_profile_key', 'account_id', 'kind', 'product_id', 'character_name',
    'units', 'credit_cost', 'status', 'mutation_snapshot', 'settings_snapshot', 'before_snapshot', 'after_snapshot',
    'wallet_transaction_id', 'refund_wallet_transaction_id', 'failure_code', 'completed_at',
])]
final class NativeAccountPurchase extends Model
{
    /** @use HasFactory<NativeAccountPurchaseFactory> */
    use HasFactory;

    /**
     * Restores immutable native terms and payment state without consulting mutable offers.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => NativePurchaseKind::class, 'status' => CharacterOperationStatus::class,
            'product_id' => 'integer', 'units' => 'integer', 'credit_cost' => 'integer',
            'mutation_snapshot' => 'array', 'settings_snapshot' => 'array',
            'before_snapshot' => 'array', 'after_snapshot' => 'array', 'completed_at' => 'immutable_datetime',
        ];
    }
}
