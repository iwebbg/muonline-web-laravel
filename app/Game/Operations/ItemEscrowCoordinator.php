<?php

namespace App\Game\Operations;

use App\Game\Contracts\CharacterInventoryEscrowRepository;
use App\Game\Contracts\ItemEscrowRepository;
use App\Game\Contracts\OperationAwareCharacterInventoryEscrowRepository;
use App\Game\Contracts\OperationAwareItemEscrowRepository;
use App\Game\Data\ExpectedInventoryItem;
use App\Market\MarketEscrowResult;

/**
 * Passes durable operation identities to capable adapters and preserves legacy dispatch.
 */
final readonly class ItemEscrowCoordinator
{
    /**
     * Extracts one exact warehouse item through its version-owned write boundary.
     */
    public function extractWarehouse(
        ItemEscrowRepository $repository,
        string $accountId,
        int $vault,
        int $slot,
        string $expectedBinary,
        string $operationKey,
    ): MarketEscrowResult {
        if ($repository instanceof OperationAwareItemEscrowRepository) {
            return $repository->extractForOperation($accountId, $vault, $slot, $expectedBinary, $operationKey);
        }

        return $repository->extract($accountId, $vault, $slot, $expectedBinary);
    }

    /**
     * Delivers one warehouse item through the original or journaled adapter protocol.
     */
    public function depositWarehouse(
        ItemEscrowRepository $repository,
        string $accountId,
        string $itemBinary,
        int $width,
        int $height,
        string $operationKey,
    ): MarketEscrowResult {
        if ($repository instanceof OperationAwareItemEscrowRepository) {
            return $repository->depositForOperation($accountId, $itemBinary, $width, $height, $operationKey);
        }

        return $repository->deposit($accountId, $itemBinary, $width, $height);
    }

    /**
     * Extracts one inventory item through the selected version's custody protocol.
     */
    public function extractInventory(
        CharacterInventoryEscrowRepository $repository,
        string $accountId,
        string $characterName,
        int $slot,
        string $expectedBinary,
        string $operationKey,
    ): MarketEscrowResult {
        if ($repository instanceof OperationAwareCharacterInventoryEscrowRepository) {
            return $repository->extractForOperation($accountId, $characterName, $slot, $expectedBinary, $operationKey);
        }

        return $repository->extract($accountId, $characterName, $slot, $expectedBinary);
    }

    /**
     * Extracts a complete inventory batch without changing legacy repository arguments.
     *
     * @param  list<ExpectedInventoryItem>  $expectedItems
     */
    public function extractInventoryBatch(
        CharacterInventoryEscrowRepository $repository,
        string $accountId,
        string $characterName,
        array $expectedItems,
        string $operationKey,
    ): MarketEscrowResult {
        if ($repository instanceof OperationAwareCharacterInventoryEscrowRepository) {
            return $repository->extractManyForOperation($accountId, $characterName, $expectedItems, $operationKey);
        }

        return $repository->extractMany($accountId, $characterName, $expectedItems);
    }

    /**
     * Delivers an inventory item with a stable receipt where the adapter supports it.
     */
    public function depositInventory(
        CharacterInventoryEscrowRepository $repository,
        string $accountId,
        string $characterName,
        string $itemBinary,
        int $width,
        int $height,
        string $operationKey,
    ): MarketEscrowResult {
        if ($repository instanceof OperationAwareCharacterInventoryEscrowRepository) {
            return $repository->depositForOperation($accountId, $characterName, $itemBinary, $width, $height, $operationKey);
        }

        return $repository->deposit($accountId, $characterName, $itemBinary, $width, $height);
    }
}
