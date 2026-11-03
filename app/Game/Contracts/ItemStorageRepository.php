<?php

namespace App\Game\Contracts;

use App\Game\Data\AccountItemStorage;

/**
 * Defines ownership-scoped reads for character inventory and account warehouses.
 */
interface ItemStorageRepository
{
    /**
     * Returns account warehouse storage without requiring a character record.
     */
    public function forAccount(string $accountId): AccountItemStorage;

    /**
     * Returns item storage only when the requested character belongs to the account.
     */
    public function forAccountAndCharacter(
        string $accountId,
        string $characterName,
    ): ?AccountItemStorage;
}
