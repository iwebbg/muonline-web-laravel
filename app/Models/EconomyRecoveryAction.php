<?php

namespace App\Models;

use Database\Factories\EconomyRecoveryActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records one immutable administrator attempt to recover an economy transfer.
 */
#[Fillable([
    'administrator_id',
    'subject_type',
    'subject_public_id',
    'account_id',
    'action',
    'before_status',
    'after_status',
    'outcome',
    'failure_code',
    'context_snapshot',
])]
final class EconomyRecoveryAction extends Model
{
    /** @use HasFactory<EconomyRecoveryActionFactory> */
    use HasFactory;

    /**
     * Returns the administrator who initiated this recovery attempt.
     *
     * @return BelongsTo<Administrator, $this>
     */
    public function administrator(): BelongsTo
    {
        return $this->belongsTo(Administrator::class);
    }

    /**
     * Casts structured context to a stable array.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['context_snapshot' => 'array'];
    }
}
