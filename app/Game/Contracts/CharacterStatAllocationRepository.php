<?php

namespace App\Game\Contracts;

use App\Reset\ResetProfileSettings;
use App\Stats\CharacterStatAllocationResult;
use App\Stats\StatAllocation;

/**
 * Defines the transactional write boundary for character stat allocation.
 */
interface CharacterStatAllocationRepository
{
    /**
     * Rechecks and applies one allocation inside a game-database transaction.
     */
    public function allocate(
        string $accountId,
        string $characterName,
        StatAllocation $allocation,
        ResetProfileSettings $settings,
    ): CharacterStatAllocationResult;
}
