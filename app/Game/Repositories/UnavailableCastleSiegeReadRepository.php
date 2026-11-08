<?php

namespace App\Game\Repositories;

use App\Game\Contracts\CastleSiegeReadRepository;
use App\Game\Data\CastleSiegeState;
use DateTimeZone;

/**
 * Disables Castle Siege reads for fixtures and game versions without this subsystem.
 */
final class UnavailableCastleSiegeReadRepository implements CastleSiegeReadRepository
{
    /**
     * Returns no cycle because the selected data source has no authoritative state.
     */
    public function state(int $mapServerGroup, DateTimeZone $timezone): ?CastleSiegeState
    {
        return null;
    }
}
