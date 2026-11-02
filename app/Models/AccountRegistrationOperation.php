<?php

namespace App\Models;

use App\AccountRegistration\AccountRegistrationStatus;
use Database\Factories\AccountRegistrationOperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Records the audit state of one legacy game-account registration attempt.
 */
#[Fillable([
    'account_id',
    'status',
    'settings_snapshot',
    'after_snapshot',
    'failure_code',
    'completed_at',
])]
final class AccountRegistrationOperation extends Model
{
    /** @use HasFactory<AccountRegistrationOperationFactory> */
    use HasFactory;

    /**
     * Casts audit state and snapshots to stable application values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AccountRegistrationStatus::class,
            'settings_snapshot' => 'array',
            'after_snapshot' => 'array',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
