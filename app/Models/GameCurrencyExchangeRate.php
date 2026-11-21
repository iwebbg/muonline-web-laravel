<?php

namespace App\Models;

use App\Economy\NativeCurrency;
use Database\Factories\GameCurrencyExchangeRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores a profile-scoped integer exchange ratio without fractional coins or floating arithmetic.
 */
#[Fillable(['game_profile_key', 'currency', 'credit_units', 'coin_units', 'minimum_units', 'maximum_units', 'enabled', 'revision'])]
final class GameCurrencyExchangeRate extends Model
{
    /** @use HasFactory<GameCurrencyExchangeRateFactory> */
    use HasFactory;

    /**
     * Returns the operator policy captured by each purchase and revision.
     *
     * @return array<string, int|string|bool>
     */
    public function snapshot(): array
    {
        return [
            'game_profile_key' => $this->game_profile_key,
            'currency' => $this->currency->value,
            'credit_units' => $this->credit_units,
            'coin_units' => $this->coin_units,
            'minimum_units' => $this->minimum_units,
            'maximum_units' => $this->maximum_units,
            'enabled' => $this->enabled,
            'revision' => $this->revision,
        ];
    }

    /**
     * Casts only the declared native currency and positive integer policy fields.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => NativeCurrency::class, 'credit_units' => 'integer', 'coin_units' => 'integer',
            'minimum_units' => 'integer', 'maximum_units' => 'integer', 'enabled' => 'boolean', 'revision' => 'integer',
        ];
    }
}
