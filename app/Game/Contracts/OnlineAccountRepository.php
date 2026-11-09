<?php

namespace App\Game\Contracts;

use App\Game\Data\OnlineAccountSession;

/**
 * Reads currently connected account sessions from a version-owned game schema.
 */
interface OnlineAccountRepository
{
    /**
     * Returns every account currently reported online with its current session start.
     *
     * @return list<OnlineAccountSession>
     */
    public function onlineSessions(): array;
}
