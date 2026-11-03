<?php

namespace App\JewelBank\Actions;

use App\Game\Contracts\ItemStorageRepository;
use App\Game\Data\ItemSlot;
use App\Game\Data\WarehouseVault;
use App\JewelBank\JewelCatalog;
use App\Models\WebStorageItem;
use App\WebStorage\Exceptions\WebStorageRejected;
use App\WebStorage\ItemStorageLocation;
use App\WebStorage\WebStorageBlockReason;
use Ramsey\Uuid\Uuid;

/**
 * Deposits a verified quantity of one jewel type as separate exact items.
 */
final readonly class StoreJewelBatch
{
    /**
     * Creates the batch action with game storage and single-item custody boundaries.
     */
    public function __construct(
        private ItemStorageRepository $itemStorageRepository,
        private JewelCatalog $jewelCatalog,
        private StoreJewel $storeJewel,
    ) {}

    /**
     * Recounts physical jewels and deposits only the requested available quantity.
     *
     * @return list<WebStorageItem>
     */
    public function execute(
        string $accountId,
        string $characterName,
        int $vault,
        ItemStorageLocation $sourceLocation,
        string $jewelCode,
        int $jewelLevel,
        int $quantity,
        string $batchIdempotencyKey,
    ): array {
        if (! $this->jewelCatalog->acceptsCode($jewelCode)) {
            throw new WebStorageRejected(WebStorageBlockReason::ItemUnavailable);
        }

        $storage = $this->itemStorageRepository->forAccountAndCharacter(
            $accountId,
            $characterName,
        );
        $matchingItems = [];

        if ($storage !== null) {
            $sourceItems = $sourceLocation === ItemStorageLocation::Inventory
                ? $storage->character->inventory
                : $this->vaultItems($storage->vaults, $vault);

            foreach ($sourceItems as $item) {
                if ($item->code() === $jewelCode
                    && ($item->level ?? 0) === $jewelLevel
                    && $this->jewelCatalog->accepts($item)) {
                    $matchingItems[] = $item;
                }
            }
        }

        if ($quantity < 1 || $quantity > count($matchingItems)) {
            throw new WebStorageRejected(WebStorageBlockReason::InsufficientQuantity);
        }

        usort(
            $matchingItems,
            static fn (ItemSlot $firstItem, ItemSlot $secondItem): int => $firstItem->slot <=> $secondItem->slot,
        );
        $itemsToStore = array_slice($matchingItems, 0, $quantity);
        $storedItems = [];

        foreach ($itemsToStore as $item) {
            $storedItems[] = $this->storeJewel->execute(
                accountId: $accountId,
                characterName: $characterName,
                vault: $vault,
                slot: $item->slot,
                idempotencyKey: $this->itemIdempotencyKey(
                    batchIdempotencyKey: $batchIdempotencyKey,
                    sourceLocation: $sourceLocation,
                    vault: $vault,
                    slot: $item->slot,
                ),
                sourceLocation: $sourceLocation,
            );
        }

        return $storedItems;
    }

    /**
     * Returns occupied items from one exact account vault.
     *
     * @param  list<WarehouseVault>  $vaults
     * @return list<ItemSlot>
     */
    private function vaultItems(array $vaults, int $vaultNumber): array
    {
        foreach ($vaults as $vault) {
            if ($vault->number === $vaultNumber) {
                return $vault->items;
            }
        }

        return [];
    }

    /**
     * Derives a stable UUID for one physical slot within a submitted batch.
     */
    private function itemIdempotencyKey(
        string $batchIdempotencyKey,
        ItemStorageLocation $sourceLocation,
        int $vault,
        int $slot,
    ): string {
        $itemIdentity = implode(':', [
            $batchIdempotencyKey,
            $sourceLocation->value,
            $vault,
            $slot,
        ]);

        return Uuid::uuid5(Uuid::NAMESPACE_URL, $itemIdentity)->toString();
    }
}
