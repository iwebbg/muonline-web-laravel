<?php

namespace App\Game\Contracts;

use App\Economy\NativeAccountMutation;
use App\Economy\NativePurchaseResult;

/**
 * Applies journaled native coin grants and account VIP without using character VIP storage.
 */
interface NativeAccountServiceRepository
{
    /**
     * Returns a stable index only for an exact owned character selected for Ruud delivery.
     */
    public function characterIndexFor(string $accountId, string $characterName): ?int;

    /**
     * Applies or acknowledges one native mutation using its durable paid-operation identity.
     */
    public function apply(string $accountId, NativeAccountMutation $mutation, string $operationKey): NativePurchaseResult;
}
