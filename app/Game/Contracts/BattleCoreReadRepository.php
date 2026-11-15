<?php

namespace App\Game\Contracts;

use App\Game\Data\BattleCoreAccountSnapshot;

/**
 * Reads private inter-server participation without executing registration or reward procedures.
 */
interface BattleCoreReadRepository
{
    /**
     * Returns only the account's configured realm records, or null when reads are disabled.
     */
    public function forAccount(string $accountId): ?BattleCoreAccountSnapshot;
}
