<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores one hashed, single-use email activation token for a legacy game account.
 */
#[Fillable([
    'account_id',
    'token_hash',
    'email_hash',
    'expires_at',
    'used_at',
])]
final class GameAccountActivation extends Model
{
    /**
     * Casts activation lifecycle timestamps for expiry and replay checks.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
        ];
    }
}
