<?php

namespace App\Game\Contracts;

/**
 * Reports whether players can currently reach the configured game server.
 */
interface GameServerStatus
{
    /**
     * Returns true only when the configured game server TCP endpoint accepts connections.
     */
    public function isOnline(): bool;
}
