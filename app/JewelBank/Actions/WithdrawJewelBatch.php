<?php

namespace App\JewelBank\Actions;

use App\JewelBank\JewelCatalog;
use App\Models\WebStorageItem;
use App\WebStorage\Actions\WithdrawWebStorageItem;
use App\WebStorage\Exceptions\WebStorageRejected;
use App\WebStorage\ItemStorageLocation;
use App\WebStorage\WebStorageBlockReason;
use App\WebStorage\WebStorageContext;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Withdraws a verified quantity of available physical jewels safely.
 */
final readonly class WithdrawJewelBatch
{
    /**
     * Creates the batch action with its exact-item withdrawal boundary.
     */
    public function __construct(
        private JewelCatalog $jewelCatalog,
        private WithdrawWebStorageItem $withdrawWebStorageItem,
    ) {}

    /**
     * Serializes account withdrawals and rejects quantities above live custody.
     *
     * @return list<WebStorageItem>
     */
    public function execute(
        string $accountId,
        string $jewelCode,
        int $jewelLevel,
        int $quantity,
        ItemStorageLocation $destinationLocation,
        ?string $destinationCharacterName,
    ): array {
        if (! $this->jewelCatalog->acceptsCode($jewelCode)) {
            throw new WebStorageRejected(WebStorageBlockReason::ItemUnavailable);
        }

        $lockName = 'jewel-bank-withdrawal:'.hash('sha256', $accountId);

        try {
            return Cache::lock($lockName, 30)->block(
                5,
                fn (): array => $this->withdrawAvailableItems(
                    accountId: $accountId,
                    jewelCode: $jewelCode,
                    jewelLevel: $jewelLevel,
                    quantity: $quantity,
                    destinationLocation: $destinationLocation,
                    destinationCharacterName: $destinationCharacterName,
                ),
            );
        } catch (LockTimeoutException) {
            throw new WebStorageRejected(WebStorageBlockReason::ItemUnavailable);
        }
    }

    /**
     * Recounts available records and returns only the requested physical items.
     *
     * @return list<WebStorageItem>
     */
    private function withdrawAvailableItems(
        string $accountId,
        string $jewelCode,
        int $jewelLevel,
        int $quantity,
        ItemStorageLocation $destinationLocation,
        ?string $destinationCharacterName,
    ): array {
        $matchingItems = WebStorageItem::query()
            ->where('account_id', $accountId)
            ->where('storage_context', WebStorageContext::JewelBank)
            ->where('status', WebStorageItemStatus::Available->value)
            ->oldest('id')
            ->get()
            ->filter(function (WebStorageItem $item) use ($jewelCode, $jewelLevel): bool {
                $snapshot = $item->item_snapshot;

                return ($snapshot['code'] ?? null) === $jewelCode
                    && (int) ($snapshot['level'] ?? 0) === $jewelLevel;
            })
            ->values();

        if ($quantity < 1 || $quantity > $matchingItems->count()) {
            throw new WebStorageRejected(WebStorageBlockReason::InsufficientQuantity);
        }

        $withdrawnItems = [];

        foreach ($matchingItems->take($quantity) as $item) {
            $withdrawnItems[] = $this->withdrawWebStorageItem->execute(
                accountId: $accountId,
                itemId: (int) $item->getKey(),
                destinationLocation: $destinationLocation,
                destinationCharacterName: $destinationCharacterName,
            );
        }

        return $withdrawnItems;
    }
}
