<?php

namespace App\Game\Contracts;

use App\Market\MarketEscrowResult;

/**
 * Requires a durable operation identity for adapters that journal native custody changes.
 */
interface OperationAwareItemEscrowRepository extends ItemEscrowRepository
{
    /**
     * Extracts exact warehouse bytes once for one stable custody operation.
     */
    public function extractForOperation(
        string $accountId,
        int $vault,
        int $slot,
        string $expectedBinary,
        string $operationKey,
    ): MarketEscrowResult;

    /**
     * Delivers exact warehouse bytes once even if the player later moves the item.
     */
    public function depositForOperation(
        string $accountId,
        string $itemBinary,
        int $width,
        int $height,
        string $operationKey,
    ): MarketEscrowResult;
}
