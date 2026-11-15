<?php

namespace App\Models;

use Database\Factories\GameCurrencyExchangeRateRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Records the operator and exact before/after exchange policy for an administrator update.
 */
#[Fillable(['game_currency_exchange_rate_id', 'administrator_id', 'revision', 'before_snapshot', 'snapshot', 'created_at'])]
final class GameCurrencyExchangeRateRevision extends Model
{
    /** @use HasFactory<GameCurrencyExchangeRateRevisionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Exposes immutable policy snapshots as arrays for the administrator audit log.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['revision' => 'integer', 'before_snapshot' => 'array', 'snapshot' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
