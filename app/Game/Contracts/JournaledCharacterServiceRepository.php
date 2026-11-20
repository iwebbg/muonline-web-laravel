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
     * Renames an owned character and native dependencies under a durable operation identity.
     */
    public function renameForOperation(string $accountId, string $oldName, string $newName, string $operationKey): CharacterServiceResult;

    /**
     * Applies an exact profile-supported class without granting advancement or losing advanced allocations.
     */
    public function changeClassForOperation(string $accountId, string $characterName, int $targetCode, string $operationKey): CharacterServiceResult;

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
