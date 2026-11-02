<?php

namespace App\Console\Commands;

use App\Models\VipEntitlement;
use App\Vip\Actions\SynchronizeVipEntitlement;
use App\Vip\VipSyncStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Synchronizes retryable application VIP entitlements in bounded batches.
 */
#[Signature('vip:sync-pending')]
#[Description('Retry pending and failed character VIP projections against MuOnline.')]
final class SynchronizePendingVip extends Command
{
    /**
     * Executes one bounded synchronization pass and reports its result.
     */
    public function handle(SynchronizeVipEntitlement $synchronizeVipEntitlement): int
    {
        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            $this->components->warn('VIP synchronization skipped: game writes are disabled.');

            return self::SUCCESS;
        }

        $synchronized = 0;
        $failed = 0;

        VipEntitlement::query()
            ->where(function (Builder $query): void {
                $query
                    ->whereIn('sync_status', [
                        VipSyncStatus::Pending->value,
                        VipSyncStatus::Failed->value,
                    ])
                    ->orWhere(function (Builder $expiredQuery): void {
                        $expiredQuery
                            ->where('sync_status', VipSyncStatus::Synced->value)
                            ->where('expires_at', '<=', now());
                    });
            })
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(function (VipEntitlement $entitlement) use (
                $synchronizeVipEntitlement,
                &$synchronized,
                &$failed,
            ): void {
                if ($synchronizeVipEntitlement->execute($entitlement)) {
                    $synchronized++;

                    return;
                }

                $failed++;
            });

        $this->components->info(
            "VIP synchronization complete: {$synchronized} synced, {$failed} pending retry.",
        );

        return self::SUCCESS;
    }
}
