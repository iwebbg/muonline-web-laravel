<?php

namespace App\ItemGrants;

/**
 * Carries the stable result of one completed warehouse item grant.
 */
final readonly class ItemGrantResult
{
    /**
     * Creates one immutable item delivery result.
     */
    public function __construct(
        public int $operationId,
        public string $accountId,
        public string $itemCode,
        public string $itemName,
        public int $serial,
    ) {}
}
