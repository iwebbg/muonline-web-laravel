<?php

namespace App\Models;

use Database\Factories\OnlineTimeAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores one account's online-session checkpoint and unclaimed Credits balance.
 */
#[Fillable([
    'account_id', 'session_started_at', 'accounted_until', 'last_observed_at',
    'reward_date', 'rewarded_seconds_today', 'rewarded_credits_today',
    'remainder_seconds', 'claimable_credits', 'lifetime_seconds',
])]
final class OnlineTimeAccount extends Model
{
    /** @use HasFactory<OnlineTimeAccountFactory> */
    use HasFactory;

    protected $primaryKey = 'account_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Casts checkpoint counters and timestamps to exact application types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'session_started_at' => 'immutable_datetime',
            'accounted_until' => 'immutable_datetime',
            'last_observed_at' => 'immutable_datetime',
            'reward_date' => 'immutable_date',
            'rewarded_seconds_today' => 'integer',
            'rewarded_credits_today' => 'integer',
            'remainder_seconds' => 'integer',
            'claimable_credits' => 'integer',
            'lifetime_seconds' => 'integer',
        ];
    }
}
