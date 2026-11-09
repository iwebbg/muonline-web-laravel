<?php

namespace App\Game\Data;

use Carbon\CarbonImmutable;

/**
 * Represents one account currently connected to the game server.
 */
final readonly class OnlineAccountSession
{
    /**
     * Creates the stable identity of one currently observed session.
     */
    public function __construct(
        public string $accountId,
        public CarbonImmutable $connectedAt,
    ) {}
}
