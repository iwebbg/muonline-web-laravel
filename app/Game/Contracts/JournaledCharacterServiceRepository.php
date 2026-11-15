<?php

namespace App\Game\Contracts;

use App\CharacterServices\CharacterServiceResult;
use App\CharacterServices\CharacterServiceType;
use App\CharacterServices\PurchasedResetPolicy;

/**
 * Applies paid progression with an immutable native receipt for interrupted-request recovery.
 */
interface JournaledCharacterServiceRepository extends CharacterServiceRepository
{
    /**
     * Applies or acknowledges one exact purchase using its durable application identity.
     *
     * Unexpected persistence failures have uncertain outcomes and must remain pending;
     * callers may refund only explicit domain rejections, never an ambiguous commit.
     */
    public function applyPurchaseForOperation(
        string $accountId,
        string $characterName,
        CharacterServiceType $type,
        int $amount,
        ?PurchasedResetPolicy $policy,
        string $operationKey,
    ): CharacterServiceResult;
}
