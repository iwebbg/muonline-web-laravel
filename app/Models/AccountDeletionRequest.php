<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores one account-owned, email-verified privacy deletion request.
 */
#[Fillable([
    'account_id',
    'email_hash',
    'code_hash',
    'status',
    'failed_attempts',
    'expires_at',
    'verified_at',
])]
final class AccountDeletionRequest extends Model
{
    /**
     * Casts the verification lifecycle timestamps used by expiry checks.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
        ];
    }
}
