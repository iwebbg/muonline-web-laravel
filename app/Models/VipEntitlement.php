<?php

namespace App\Models;

use App\Vip\VipSyncStatus;
use Database\Factories\VipEntitlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores the current application-owned VIP expiry for one MuOnline character.
 */
#[Fillable([
    'account_id',
    'character_name',
    'vip_plan_id',
    'starts_at',
    'expires_at',
    'sync_status',
    'game_synced_expires_at',
    'last_sync_attempt_at',
    'sync_attempts',
    'last_sync_error',
])]
final class VipEntitlement extends Model
{
    /** @use HasFactory<VipEntitlementFactory> */
    use HasFactory;

    /**
     * Returns the latest plan responsible for this entitlement.
     *
     * @return BelongsTo<VipPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(VipPlan::class, 'vip_plan_id')->withTrashed();
    }

    /**
     * Casts synchronization and expiry state to typed values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'sync_status' => VipSyncStatus::class,
            'game_synced_expires_at' => 'immutable_datetime',
            'last_sync_attempt_at' => 'immutable_datetime',
            'sync_attempts' => 'integer',
        ];
    }
}
