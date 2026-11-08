<?php

namespace App\Game\Contracts;

use App\Game\Data\CastleSiegeState;
use DateTimeZone;

/**
 * Defines the read-only Castle Siege cycle state exposed by supported game databases.
 */
interface CastleSiegeReadRepository
{
    /**
     * Returns the server-owned Castle Siege cycle for one map server group.
     */
    public function state(int $mapServerGroup, DateTimeZone $timezone): ?CastleSiegeState;
}
