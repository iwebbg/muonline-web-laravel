<?php

namespace App\Models;

use App\AccountCredentials\AccountCredentialStatus;
use Database\Factories\AccountCredentialOperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Records the durable audit state of one account credential update attempt.
 */
#[Fillable([
    'account_id',
    'status',
    'settings_snapshot',
    'before_snapshot',
    'after_snapshot',
    'failure_code',
    'completed_at',
])]
final class AccountCredentialOperation extends Model
{
    /** @use HasFactory<AccountCredentialOperationFactory> */
    use HasFactory;

    /**
     * Casts operation state and audit payloads to stable application values.
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
