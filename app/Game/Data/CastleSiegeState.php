<?php

namespace App\Game\Data;

use DateTimeImmutable;

/**
 * Carries the authoritative Castle Siege cycle without exposing legacy column names.
 */
final readonly class CastleSiegeState
{
    /**
     * Creates one immutable snapshot read from the game database.
     */
    public function __construct(
        public int $mapServerGroup,
        public DateTimeImmutable $cycleStartsAt,
        public DateTimeImmutable $cycleEndsAt,
        public bool $siegeEnded,
        public bool $castleOccupied,
        public ?string $ownerGuild,
    ) {}
}
