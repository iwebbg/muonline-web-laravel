<?php

namespace App\WebStorage\Actions;

use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\ItemStorageRepository;
use App\Game\Data\ItemSlot;
use App\Game\Data\WarehouseVault;
use App\Models\WebStorageItem;
use App\WebStorage\Exceptions\WebStorageRejected;
use App\WebStorage\ItemStorageLocation;
use App\WebStorage\WebStorageBlockReason;
use App\WebStorage\WebStorageItemStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * Reserves one exact warehouse item before moving it into a player's web vault.
 */
final readonly class StoreWebStorageItem
{
    /**
     * Creates the storage action with ownership, storage and synchronization boundaries.
     */
    public function __construct(
        private AccountReadRepository $accountReadRepository,
        private ItemStorageRepository $itemStorageRepository,
        private SynchronizeWebStorageItem $synchronizeWebStorageItem,
    ) {}

    /**
     * Creates one idempotent web storage record and attempts exact extraction.
     */
    public function execute(
        string $accountId,
        string $characterName,
        int $vault,
        int $slot,
        string $idempotencyKey,
        ItemStorageLocation $sourceLocation = ItemStorageLocation::Warehouse,
    ): WebStorageItem {
        $requestHash = $this->requestHash(
            accountId: $accountId,
            characterName: $characterName,
            vault: $vault,
            slot: $slot,
            sourceLocation: $sourceLocation,
        );
        $existingItem = WebStorageItem::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingItem instanceof WebStorageItem) {
            return $this->resolveExisting($existingItem, $requestHash);
        }

        if (! (bool) config('game.writes_enabled', false)
            || (bool) config('database.connections.game.readonly', true)) {
            throw new WebStorageRejected(WebStorageBlockReason::WritesDisabled);
        }

        $character = $this->accountReadRepository->characterFor($accountId, $characterName);

        if ($character === null) {
            throw new WebStorageRejected(WebStorageBlockReason::CharacterNotFound);
        }

        if ($character->online) {
            throw new WebStorageRejected(WebStorageBlockReason::CharacterOnline);
        }

        $storage = $this->itemStorageRepository->forAccountAndCharacter($accountId, $characterName);
        $warehouseItem = match ($sourceLocation) {
            ItemStorageLocation::Warehouse => $storage === null
                ? null
                : $this->warehouseItem($storage->vaults, $vault, $slot),
            ItemStorageLocation::Inventory => $storage === null
                ? null
                : $this->inventoryItem($storage->character->inventory, $slot),
        };

        if (! $warehouseItem instanceof ItemSlot) {
            throw new WebStorageRejected(WebStorageBlockReason::ItemNotFound);
        }

        $fingerprint = hash('sha256', $warehouseItem->binary());

        if (WebStorageItem::query()->where('escrow_key', $fingerprint)->exists()) {
            throw new WebStorageRejected(WebStorageBlockReason::ItemChanged);
        }

        try {
            $item = WebStorageItem::query()->create([
                'public_id' => (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'account_id' => $accountId,
                'source_character_name' => $characterName,
                'source_location' => $sourceLocation,
                'source_vault' => $vault,
                'source_slot' => $slot,
                'item_binary' => $warehouseItem->binary(),
                'item_fingerprint' => $fingerprint,
                'escrow_key' => $fingerprint,
                'item_snapshot' => $warehouseItem->toArray(),
                'status' => WebStorageItemStatus::Storing,
                'sync_attempts' => 0,
            ]);
        } catch (QueryException $exception) {
            $existingItem = WebStorageItem::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingItem instanceof WebStorageItem) {
                return $this->resolveExisting($existingItem, $requestHash);
            }

            if (WebStorageItem::query()->where('escrow_key', $fingerprint)->exists()) {
                throw new WebStorageRejected(WebStorageBlockReason::ItemChanged);
            }

            throw $exception;
        }

        $this->synchronizeWebStorageItem->execute($item);

        return $item->refresh();
    }

    /**
     * Finds one exact item in the selected warehouse vault.
     *
     * @param  list<WarehouseVault>  $vaults
     */
    private function warehouseItem(array $vaults, int $vaultNumber, int $slotNumber): ?ItemSlot
    {
        foreach ($vaults as $vault) {
            if ($vault->number !== $vaultNumber) {
                continue;
            }

            foreach ($vault->items as $item) {
                if ($item->slot === $slotNumber) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * Finds one exact item in the selected character inventory bag.
     *
     * @param  list<ItemSlot>  $items
     */
    private function inventoryItem(array $items, int $slotNumber): ?ItemSlot
    {
        foreach ($items as $item) {
            if ($item->slot === $slotNumber) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Returns an identical replay or rejects one key reused for another item.
     */
    private function resolveExisting(WebStorageItem $item, string $requestHash): WebStorageItem
    {
        if (! hash_equals($item->request_hash, $requestHash)) {
            throw new WebStorageRejected(WebStorageBlockReason::IdempotencyConflict);
        }

        return $item;
    }

    /**
     * Produces the deterministic fingerprint bound to one storage request.
     */
    private function requestHash(
        string $accountId,
        string $characterName,
        int $vault,
        int $slot,
        ItemStorageLocation $sourceLocation,
    ): string {
        $payload = json_encode([
            'account_id' => $accountId,
            'character_name' => $characterName,
            'vault' => $vault,
            'slot' => $slot,
            'source_location' => $sourceLocation->value,
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $payload);
    }
}
