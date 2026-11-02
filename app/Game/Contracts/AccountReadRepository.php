<?php

namespace App\Game\Contracts;

use App\Game\Data\AccountCharacter;

/**
 * Defines private character reads scoped to one authenticated game account.
 */
interface AccountReadRepository
{
    /**
     * Returns only characters owned by the supplied MuOnline account identifier.
     *
     * @return list<AccountCharacter>
     */
    public function charactersFor(string $accountId): array;

    /**
     * Returns one character only when it belongs to the supplied account.
     */
    public function characterFor(string $accountId, string $characterName): ?AccountCharacter;
}
