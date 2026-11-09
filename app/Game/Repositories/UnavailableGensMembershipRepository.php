<?php

namespace App\Game\Repositories;

use App\Game\Contracts\GensMembershipRepository;

/**
 * Disables Gens reads for game profiles without a verified standard adapter.
 */
final class UnavailableGensMembershipRepository implements GensMembershipRepository
{
    /**
     * Returns no memberships because the selected profile does not support Gens.
     *
     * @return array<empty, never>
     */
    public function forAccount(string $accountId): array
    {
        return [];
    }
}
