<?php

namespace App\Game\Status;

use App\Game\Contracts\GameServerStatus;

/**
 * Keeps interface previews online when deterministic fixture data is selected.
 */
final readonly class FixtureGameServerStatus implements GameServerStatus
{
    /**
     * Returns the online state represented by the fixture server profile.
     */
    public function isOnline(): bool
    {
        return true;
    }
}
