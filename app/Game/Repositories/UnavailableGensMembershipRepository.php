<?php

namespace App\Game\Repositories;

use App\Game\Contracts\GensMembershipRepository;
use App\Game\Data\GensRankingPage;
use App\Game\Data\GensRankingQuery;

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

    /**
     * Returns an empty page because the selected profile does not support Gens.
     */
    public function rankings(GensRankingQuery $query): GensRankingPage
    {
        return new GensRankingPage(
            entries: [],
            total: 0,
            page: $query->page,
            perPage: $query->perPage,
            lastPage: 1,
        );
    }
}
