<?php

namespace App\Game\Repositories;

use App\Game\Contracts\MasterProgressionRepository;

/**
 * Disables Master progression reads for game versions without the standard system.
 */
final class UnavailableMasterProgressionRepository implements MasterProgressionRepository
{
    /**
     * Returns no progression because the selected profile has no verified adapter.
     *
     * @return array<empty, never>
     */
    public function forAccount(string $accountId): array
    {
        return [];
    }
}
