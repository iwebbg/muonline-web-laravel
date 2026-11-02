<?php

namespace App\Models;

use App\VoteRewards\VoteClaimStatus;
use Database\Factories\VoteClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records one reviewable vote visit and its immutable reward settlement audit.
 */
#[Fillable([
    'public_id',
    'idempotency_key',
    'vote_site_id',
    'account_id',
    'ip_hash',
    'reward_credits',
    'reward_zen',
    'status',
    'wallet_transaction_id',
    'zen_balance_before',
    'zen_balance_after',
    'reviewed_by_administrator_id',
    'rejection_reason',
    'initiated_at',
    'expires_at',
    'reviewed_at',
    'rewarded_at',
])]
final class VoteClaim extends Model
{
    /** @use HasFactory<VoteClaimFactory> */
    use HasFactory;

    /**
     * Returns the vote destination whose reward policy was snapshotted.
     *
     * @return BelongsTo<VoteSite, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(VoteSite::class, 'vote_site_id')->withTrashed();
    }

    /**
     * Returns the immutable Credits ledger entry created for this reward.
     *
     * @return BelongsTo<WalletTransaction, $this>
     */
    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    /**
     * Returns the administrator who accepted or refused this claim.
     *
     * @return BelongsTo<Administrator, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'reviewed_by_administrator_id');
    }

    /**
     * Uses the random public identifier in review routes instead of a sequence number.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Casts reward state and timestamps to stable domain values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reward_credits' => 'integer',
            'reward_zen' => 'integer',
            'status' => VoteClaimStatus::class,
            'zen_balance_before' => 'integer',
            'zen_balance_after' => 'integer',
            'initiated_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'rewarded_at' => 'immutable_datetime',
        ];
    }
}
