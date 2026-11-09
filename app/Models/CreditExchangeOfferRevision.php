<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Records one immutable before and after snapshot of a Credits exchange offer.
 */
#[Fillable([
    'credit_exchange_offer_id', 'revision', 'action', 'before_snapshot',
    'after_snapshot', 'changed_by_administrator_id',
])]
final class CreditExchangeOfferRevision extends Model
{
    public const UPDATED_AT = null;

    /**
     * Casts revision data for safe audit presentation.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'changed_by_administrator_id' => 'integer',
        ];
    }
}
