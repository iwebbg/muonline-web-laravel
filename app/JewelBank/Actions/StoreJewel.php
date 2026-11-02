<?php

namespace App\JewelBank\Actions;

use App\Game\Contracts\ItemStorageRepository;
use App\Game\Data\ItemSlot;
use App\JewelBank\JewelCatalog;
use App\Models\WebStorageItem;
use App\WebStorage\Actions\StoreWebStorageItem;
use App\WebStorage\Exceptions\WebStorageRejected;
use App\WebStorage\WebStorageBlockReason;

/**
 * Validates a physical jewel before delegating exact-byte web-vault storage.
 */
final readonly class StoreJewel
{
    /**
     * Creates the action with inspection and recoverable movement boundaries.
     */
    public function __construct(
        private ItemStorageRepository $itemStorageRepository,
        private JewelCatalog $jewelCatalog,
        private StoreWebStorageItem $storeWebStorageItem,
    ) {}

    /**
     * Stores one supported jewel without converting it to a synthetic balance.
     */
    public function execute(
        string $accountId,
        string $characterName,
        int $vault,
        int $slot,
        string $idempotencyKey,
    ): WebStorageItem {
        $existingItem = WebStorageItem::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingItem instanceof WebStorageItem) {
            $existingCode = $existingItem->item_snapshot['code'] ?? null;

            if (! is_string($existingCode) || ! $this->jewelCatalog->acceptsCode($existingCode)) {
                throw new WebStorageRejected(WebStorageBlockReason::IdempotencyConflict);
            }

            return $this->storeWebStorageItem->execute(
                accountId: $accountId,
                characterName: $characterName,
                vault: $vault,
                slot: $slot,
                idempotencyKey: $idempotencyKey,
            );
        }

        $storage = $this->itemStorageRepository->forAccountAndCharacter(
            $accountId,
            $characterName,
        );
        $selectedItem = null;

        foreach ($storage?->vaults ?? [] as $warehouseVault) {
            if ($warehouseVault->number !== $vault) {
                continue;
            }

            foreach ($warehouseVault->items as $item) {
                if ($item->slot === $slot) {
                    $selectedItem = $item;
                }
            }
        }

        if (! $selectedItem instanceof ItemSlot || ! $this->jewelCatalog->accepts($selectedItem)) {
            throw new WebStorageRejected(WebStorageBlockReason::ItemUnavailable);
        }

        return $this->storeWebStorageItem->execute(
            accountId: $accountId,
            characterName: $characterName,
            vault: $vault,
            slot: $slot,
            idempotencyKey: $idempotencyKey,
        );
    }
}
