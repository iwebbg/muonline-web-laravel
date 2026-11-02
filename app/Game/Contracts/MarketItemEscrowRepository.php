<?php

namespace App\Game\Contracts;

use App\Market\MarketEscrowResult;

/**
 * Defines idempotent warehouse extraction and return writes for market escrow.
 */
interface MarketItemEscrowRepository
{
    /**
     * Removes the exact expected item bytes from one owned warehouse slot.
     */
    public function extract(
        string $accountId,
        int $vault,
        int $slot,
        string $expectedBinary,
    ): MarketEscrowResult;

    /**
     * Returns one escrowed item to the account's active warehouse without duplication.
     */
    public function deposit(
        string $accountId,
        string $itemBinary,
        int $width,
        int $height,
    ): MarketEscrowResult;
}
