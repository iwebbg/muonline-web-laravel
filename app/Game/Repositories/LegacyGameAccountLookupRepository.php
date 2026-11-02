<?php

namespace App\Game\Repositories;

use App\Auth\GameAccountUserProvider;
use App\Game\Contracts\GameAccountLookupRepository;

/**
 * Checks legacy account existence through the configured authentication provider.
 */
final readonly class LegacyGameAccountLookupRepository implements GameAccountLookupRepository
{
    /**
     * Creates the lookup adapter around the password-safe provider boundary.
     */
    public function __construct(
        private GameAccountUserProvider $accountProvider,
    ) {}

    /**
     * Determines whether one exact legacy account identifier exists.
     */
    public function exists(string $accountId): bool
    {
        return $this->accountProvider->retrieveById($accountId) !== null;
    }
}
