<?php

namespace App\Game\Contracts;

use App\Market\MarketEscrowResult;

/**
 * Defines exact-byte extraction and deposit writes for an owned character bag.
 */
interface CharacterInventoryEscrowRepository
{
    /**
     * Removes the exact expected item from one offline owned character bag slot.
     */
    public function extract(
        string $accountId,
        string $characterName,
        int $slot,
        string $expectedBinary,
    ): MarketEscrowResult;

    /**
     * Deposits one escrowed item into the first free rectangle in the character bag.
     */
    public function deposit(
        string $accountId,
        string $characterName,
        string $itemBinary,
        int $width,
        int $height,
    ): MarketEscrowResult;
}
