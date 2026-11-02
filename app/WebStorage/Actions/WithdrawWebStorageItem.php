<?php

namespace App\WebStorage\Actions;

use App\Models\WebStorageItem;
use App\WebStorage\Exceptions\WebStorageRejected;
use App\WebStorage\WebStorageBlockReason;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Support\Facades\DB;

/**
 * Withdraws one owned web-vault item back to the active warehouse safely.
 */
final readonly class WithdrawWebStorageItem
{
    /**
     * Creates the withdrawal action with its retryable synchronizer.
     */
    public function __construct(
        private SynchronizeWebStorageItem $synchronizeWebStorageItem,
    ) {}

    /**
     * Starts one owner-scoped withdrawal and attempts immediate delivery.
     */
    public function execute(string $accountId, int $itemId): WebStorageItem
    {
        $item = DB::transaction(function () use ($accountId, $itemId): WebStorageItem {
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

            if (! in_array(
                $lockedItem->status,
                [WebStorageItemStatus::Available, WebStorageItemStatus::WithdrawalPending],
                true,
            )) {
                throw new WebStorageRejected(WebStorageBlockReason::ItemUnavailable);
            }

            $lockedItem->update([
                'status' => WebStorageItemStatus::WithdrawalPending,
            ]);

            return $lockedItem;
        }, attempts: 3);

        $this->synchronizeWebStorageItem->execute($item);

        return $item->refresh();
    }
}
