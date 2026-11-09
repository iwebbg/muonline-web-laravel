<?php

namespace App\Game\Contracts;

use App\Game\Data\MasterProgression;

/**
 * Defines account-scoped reads for standard Master Level progression.
 */
interface MasterProgressionRepository
{
    /**
     * Returns Master progression keyed by character name for one owned account.
     *
     * @return array<string, MasterProgression>
     */
    public function forAccount(string $accountId): array;
}
