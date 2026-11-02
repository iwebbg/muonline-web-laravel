<?php

namespace App\Game\Contracts;

use App\Market\MarketEscrowResult;

/**
 * Defines idempotent exact-byte extraction and deposit writes for item escrow.
 */
interface ItemEscrowRepository
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
     * Deposits one escrowed item without duplicating an already delivered serial.
     */
    public function deposit(
        string $accountId,
        string $itemBinary,
        int $width,
        int $height,
    ): MarketEscrowResult;
}
