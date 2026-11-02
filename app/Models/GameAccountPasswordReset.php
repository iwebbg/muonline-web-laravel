<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores one hashed, expiring, single-use game account recovery token.
 */
#[Fillable(['account_id', 'token_hash', 'email_hash', 'expires_at', 'used_at'])]
#[Hidden(['token_hash', 'email_hash'])]
final class GameAccountPasswordReset extends Model
{
    /**
     * Casts token lifecycle timestamps to immutable values.
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
