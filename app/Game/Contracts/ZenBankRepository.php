<?php

namespace App\Game\Contracts;

use App\ZenBank\ZenProjectionResult;

/**
 * Defines warehouse Zen reads and idempotent compare-and-set projections.
 */
interface ZenBankRepository
{
    /**
     * Returns one account warehouse Zen balance or null when storage is missing.
     */
    public function currentBalance(string $accountId): ?int;

    /**
     * Changes warehouse Zen only when its value matches the expected transition.
     */
    public function project(
        string $accountId,
        int $expectedBalance,
        int $resultingBalance,
    ): ZenProjectionResult;
}
