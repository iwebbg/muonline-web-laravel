<?php

namespace App\Game\Contracts;

use App\Game\Data\AccountItemStorage;

/**
 * Defines ownership-scoped reads for character inventory and account warehouses.
 */
interface ItemStorageRepository
{
    /**
     * Returns item storage only when the requested character belongs to the account.
     */
    public function forAccountAndCharacter(
        string $accountId,
        string $characterName,
    ): ?AccountItemStorage;
}
