<?php

namespace App\Models;

use App\AccountCredentials\AccountCredentialStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Records password-free request and completion state for account recovery.
 */
#[Fillable([
    'account_id',
    'operation_type',
    'status',
    'settings_snapshot',
    'before_snapshot',
    'after_snapshot',
    'failure_code',
    'completed_at',
])]
final class AccountPasswordRecoveryOperation extends Model
{
    /**
     * Casts operation status and safe audit snapshots.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AccountCredentialStatus::class,
            'settings_snapshot' => 'array',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
