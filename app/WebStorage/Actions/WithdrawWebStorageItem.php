<?php

namespace App\WebStorage\Actions;

use App\Game\Contracts\AccountReadRepository;
use App\Models\WebStorageItem;
use App\WebStorage\Exceptions\WebStorageRejected;
use App\WebStorage\ItemStorageLocation;
use App\WebStorage\WebStorageBlockReason;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Support\Facades\DB;

/**
 * Withdraws one owned web-vault item to a verified game destination safely.
 */
final readonly class WithdrawWebStorageItem
{
    /**
     * Creates the withdrawal action with its retryable synchronizer.
     */
    public function __construct(
        private SynchronizeWebStorageItem $synchronizeWebStorageItem,
        private AccountReadRepository $accountReadRepository,
    ) {}

    /**
     * Starts one owner-scoped withdrawal and attempts immediate delivery.
     */
    public function execute(
        string $accountId,
        int $itemId,
        ItemStorageLocation $destinationLocation = ItemStorageLocation::Warehouse,
        ?string $destinationCharacterName = null,
    ): WebStorageItem {
        if (
            $destinationLocation === ItemStorageLocation::Inventory
            && ($destinationCharacterName === null || $destinationCharacterName === '')
        ) {
            throw new WebStorageRejected(WebStorageBlockReason::CharacterNotFound);
        }

        if (
            $destinationLocation === ItemStorageLocation::Inventory
            && $this->accountReadRepository->characterFor(
                $accountId,
                (string) $destinationCharacterName,
            ) === null
        ) {
            throw new WebStorageRejected(WebStorageBlockReason::CharacterNotFound);
        }

        $item = DB::transaction(function () use (
            $accountId,
            $itemId,
            $destinationLocation,
            $destinationCharacterName,
        ): WebStorageItem {
            $lockedItem = WebStorageItem::query()
                ->whereKey($itemId)
                ->where('account_id', $accountId)
                ->lockForUpdate()
                ->first();

            if (! $lockedItem instanceof WebStorageItem) {
                throw new WebStorageRejected(WebStorageBlockReason::ItemUnavailable);
            }

            if ($lockedItem->status === WebStorageItemStatus::Withdrawn) {
                return $lockedItem;
            }

            if ($lockedItem->status === WebStorageItemStatus::WithdrawalPending) {
                return $lockedItem;
            }

            if ($lockedItem->status !== WebStorageItemStatus::Available) {
                throw new WebStorageRejected(WebStorageBlockReason::ItemUnavailable);
            }

            $lockedItem->update([
                'status' => WebStorageItemStatus::WithdrawalPending,
                'withdrawal_location' => $destinationLocation,
                'withdrawal_character_name' => $destinationLocation === ItemStorageLocation::Inventory
                    ? $destinationCharacterName
                    : null,
            ]);

            return $lockedItem;
        }, attempts: 3);

        $this->synchronizeWebStorageItem->execute($item);

        return $item->refresh();
    }
}
