<?php

namespace App\Vip\Actions;

use App\Game\Contracts\CharacterVipRepository;
use App\Models\VipEntitlement;
use App\Vip\Exceptions\VipPurchaseRejected;
use App\Vip\VipSyncStatus;
use Throwable;

/**
 * Projects one application-owned VIP entitlement into the MuOnline database.
 */
final readonly class SynchronizeVipEntitlement
{
    /**
     * Creates the synchronization action with the version-aware game writer.
     */
    public function __construct(
        private CharacterVipRepository $characterVipRepository,
    ) {}

    /**
     * Attempts one synchronization and persists only safe diagnostic state.
     */
    public function execute(VipEntitlement $entitlement): bool
    {
        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            $this->markFailed($entitlement, 'writes_disabled');

            return false;
        }

        try {
            $this->characterVipRepository->synchronize(
                accountId: $entitlement->account_id,
                characterName: $entitlement->character_name,
                expiresAt: $entitlement->expires_at,
            );
        } catch (VipPurchaseRejected $exception) {
            $this->markFailed($entitlement, $exception->reason->value);

            return false;
        } catch (Throwable) {
            $this->markFailed($entitlement, 'game_sync_failed');

            return false;
        }

        $syncStatus = $entitlement->expires_at->isFuture()
            ? VipSyncStatus::Synced
            : VipSyncStatus::Expired;

        $entitlement->forceFill([
            'sync_status' => $syncStatus,
            'game_synced_expires_at' => $syncStatus === VipSyncStatus::Synced
                ? $entitlement->expires_at
                : null,
            'last_sync_attempt_at' => now(),
            'sync_attempts' => $entitlement->sync_attempts + 1,
            'last_sync_error' => null,
        ])->save();

        return true;
    }

    /**
     * Records a retryable failed projection without exposing infrastructure details.
     */
    private function markFailed(VipEntitlement $entitlement, string $reason): void
    {
        $entitlement->forceFill([
            'sync_status' => VipSyncStatus::Failed,
            'last_sync_attempt_at' => now(),
            'sync_attempts' => $entitlement->sync_attempts + 1,
            'last_sync_error' => $reason,
        ])->save();
    }
}
