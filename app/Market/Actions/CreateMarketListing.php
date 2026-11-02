<?php

namespace App\Market\Actions;

use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\ItemStorageRepository;
use App\Game\Data\ItemSlot;
use App\Game\Data\WarehouseVault;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingBlockReason;
use App\Market\MarketListingStatus;
use App\Models\MarketListing;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * Reserves one exact warehouse item before moving it into recoverable escrow.
 */
final readonly class CreateMarketListing
{
    /**
     * Creates the listing action with ownership, storage and escrow boundaries.
     */
    public function __construct(
        private AccountReadRepository $accountReadRepository,
        private ItemStorageRepository $itemStorageRepository,
        private SynchronizeMarketListing $synchronizeMarketListing,
    ) {}

    /**
     * Creates one idempotent listing and immediately attempts item extraction.
     */
    public function execute(
        string $accountId,
        string $characterName,
        int $vault,
        int $slot,
        int $priceCredits,
        string $idempotencyKey,
    ): MarketListing {
        $requestHash = $this->requestHash(
            accountId: $accountId,
            characterName: $characterName,
            vault: $vault,
            slot: $slot,
            priceCredits: $priceCredits,
        );
        $existingListing = MarketListing::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingListing instanceof MarketListing) {
            return $this->resolveExisting($existingListing, $requestHash);
        }

        $writesEnabled = (bool) config('game.writes_enabled', false);
        $connectionReadOnly = (bool) config('database.connections.game.readonly', true);

        if (! $writesEnabled || $connectionReadOnly) {
            throw new MarketListingRejected(MarketListingBlockReason::WritesDisabled);
        }

        $character = $this->accountReadRepository->characterFor($accountId, $characterName);

        if ($character === null) {
            throw new MarketListingRejected(MarketListingBlockReason::CharacterNotFound);
        }

        if ($character->online) {
            throw new MarketListingRejected(MarketListingBlockReason::CharacterOnline);
        }

        $storage = $this->itemStorageRepository->forAccountAndCharacter(
            $accountId,
            $characterName,
        );
        $item = $storage === null
            ? null
            : $this->warehouseItem($storage->vaults, $vault, $slot);

        if (! $item instanceof ItemSlot) {
            throw new MarketListingRejected(MarketListingBlockReason::ItemNotFound);
        }

        $itemFingerprint = hash('sha256', $item->binary());
        $duplicateEscrow = MarketListing::query()
            ->where('escrow_key', $itemFingerprint)
            ->exists();

        if ($duplicateEscrow) {
            throw new MarketListingRejected(MarketListingBlockReason::ItemChanged);
        }

        try {
            $listing = MarketListing::query()->create([
                'public_id' => (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'seller_account_id' => $accountId,
                'source_character_name' => $characterName,
                'source_vault' => $vault,
                'source_slot' => $slot,
                'item_binary' => $item->binary(),
                'item_fingerprint' => $itemFingerprint,
                'escrow_key' => $itemFingerprint,
                'item_snapshot' => $item->toArray(),
                'price_credits' => $priceCredits,
                'status' => MarketListingStatus::Extracting,
                'sync_attempts' => 0,
                'expires_at' => now()->addDays(
                    (int) config('game.market_listing_duration_days', 30),
                ),
            ]);
        } catch (QueryException $exception) {
            $existingListing = MarketListing::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingListing instanceof MarketListing) {
                return $this->resolveExisting($existingListing, $requestHash);
            }

            if (MarketListing::query()->where('escrow_key', $itemFingerprint)->exists()) {
                throw new MarketListingRejected(MarketListingBlockReason::ItemChanged);
            }

            throw $exception;
        }

        $this->synchronizeMarketListing->execute($listing);

        return $listing->refresh();
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
     * Returns an identical replay or rejects a reused request key.
     */
    private function resolveExisting(MarketListing $listing, string $requestHash): MarketListing
    {
        if (! hash_equals($listing->request_hash, $requestHash)) {
            throw new MarketListingRejected(MarketListingBlockReason::IdempotencyConflict);
        }

        return $listing;
    }

    /**
     * Produces the deterministic fingerprint bound to one listing request key.
     */
    private function requestHash(
        string $accountId,
        string $characterName,
        int $vault,
        int $slot,
        int $priceCredits,
    ): string {
        $payload = json_encode([
            'account_id' => $accountId,
            'character_name' => $characterName,
            'vault' => $vault,
            'slot' => $slot,
            'price_credits' => $priceCredits,
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $payload);
    }
}
