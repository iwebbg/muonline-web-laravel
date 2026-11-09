<?php

namespace App\Game\Repositories;

use App\Game\Contracts\MasterProgressionRepository;
use App\Game\Data\MasterProgression;

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

    /**
     * Returns no public progression for an unsupported profile.
     */
    public function forCharacter(string $characterName): ?MasterProgression
    {
        return null;
    }
}
