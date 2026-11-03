<?php

namespace App\WebStorage\Actions;

use App\Game\Contracts\CharacterInventoryEscrowRepository;
use App\Game\Contracts\ItemEscrowRepository;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingBlockReason;
use App\Models\WebStorageItem;
use App\WebStorage\ItemStorageLocation;
use App\WebStorage\WebStorageItemStatus;
use Throwable;

/**
 * Projects one recoverable web storage transition into its exact game storage.
 */
final readonly class SynchronizeWebStorageItem
{
    /**
     * Creates the synchronizer with exact-byte warehouse and inventory writers.
     */
    public function __construct(
        private ItemEscrowRepository $itemEscrowRepository,
        private CharacterInventoryEscrowRepository $characterInventoryEscrowRepository,
    ) {}

    /**
     * Attempts storage extraction or withdrawal while preserving retryable state.
     */
    public function execute(WebStorageItem $item): bool
    {
        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            $this->recordFailure($item, 'writes_disabled');

            return false;
        }

        try {
            if ($item->status === WebStorageItemStatus::Storing) {
                if ($item->source_location === ItemStorageLocation::Inventory) {
                    $this->characterInventoryEscrowRepository->extract(
                        accountId: $item->account_id,
                        characterName: $item->source_character_name,
                        slot: $item->source_slot,
                        expectedBinary: $item->item_binary,
                    );
                } else {
                    $this->itemEscrowRepository->extract(
                        accountId: $item->account_id,
                        vault: $item->source_vault,
                        slot: $item->source_slot,
                        expectedBinary: $item->item_binary,
                    );
                }
                $item->forceFill([
                    'status' => WebStorageItemStatus::Available,
                    'stored_at' => now(),
                    'last_sync_error' => null,
                ]);
            } elseif ($item->status === WebStorageItemStatus::WithdrawalPending) {
                $snapshot = $item->item_snapshot;
                $destinationLocation = $item->withdrawal_location
                    ?? ItemStorageLocation::Warehouse;

                if ($destinationLocation === ItemStorageLocation::Inventory) {
                    $this->characterInventoryEscrowRepository->deposit(
                        accountId: $item->account_id,
                        characterName: (string) $item->withdrawal_character_name,
                        itemBinary: $item->item_binary,
                        width: (int) ($snapshot['width'] ?? 1),
                        height: (int) ($snapshot['height'] ?? 1),
                    );
                } else {
                    $this->itemEscrowRepository->deposit(
                        accountId: $item->account_id,
                        itemBinary: $item->item_binary,
                        width: (int) ($snapshot['width'] ?? 1),
                        height: (int) ($snapshot['height'] ?? 1),
                    );
                }
                $item->forceFill([
                    'status' => WebStorageItemStatus::Withdrawn,
                    'escrow_key' => null,
                    'withdrawn_at' => now(),
                    'last_sync_error' => null,
                ]);
            } else {
                return true;
            }
        } catch (MarketListingRejected $exception) {
            $terminalStorageFailure = $item->status === WebStorageItemStatus::Storing
                && in_array(
                    $exception->reason,
                    [
                        MarketListingBlockReason::ItemChanged,
                        MarketListingBlockReason::ItemNotFound,
                        MarketListingBlockReason::WarehouseNotFound,
                    ],
                    true,
                );

            if ($terminalStorageFailure) {
                $item->forceFill([
                    'status' => WebStorageItemStatus::Failed,
                    'escrow_key' => null,
                ]);
            }

            $this->recordFailure($item, $exception->reason->value);

            return false;
        } catch (Throwable) {
            $this->recordFailure($item, 'game_sync_failed');

            return false;
        }

        $item->forceFill([
            'sync_attempts' => $item->sync_attempts + 1,
            'last_sync_attempt_at' => now(),
        ])->save();

        return true;
    }

    /**
     * Records one safe synchronization failure without infrastructure details.
     */
    private function recordFailure(WebStorageItem $item, string $reason): void
    {
        $item->forceFill([
            'sync_attempts' => $item->sync_attempts + 1,
            'last_sync_attempt_at' => now(),
            'last_sync_error' => $reason,
        ])->save();
    }
}
