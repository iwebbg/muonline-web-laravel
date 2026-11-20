<?php

namespace App\Game\Contracts;

use App\Game\Data\ExpectedInventoryItem;
use App\Market\MarketEscrowResult;

/**
 * Adds durable operation identities to native inventory custody and batch extraction.
 */
interface OperationAwareCharacterInventoryEscrowRepository extends CharacterInventoryEscrowRepository
{
    /**
     * Extracts one exact owned bag item once for a stable operation.
     */
    public function extractForOperation(
        string $accountId,
        string $characterName,
        int $slot,
        string $expectedBinary,
        string $operationKey,
    ): MarketEscrowResult;

    /**
     * Extracts a complete owned bag batch and its operation receipt atomically.
     *
     * @param  list<ExpectedInventoryItem>  $expectedItems
     */
    public function extractManyForOperation(
        string $accountId,
        string $characterName,
        array $expectedItems,
        string $operationKey,
    ): MarketEscrowResult;

    /**
     * Delivers one exact owned bag item once for a stable operation.
     */
    public function depositForOperation(
        string $accountId,
        string $characterName,
        string $itemBinary,
        int $width,
        int $height,
        string $operationKey,
    ): MarketEscrowResult;
}
