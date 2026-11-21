<?php

namespace App\Game\Data;

/**
 * Carries private Battle Core registration records and unclaimed reward quantities.
 */
final readonly class BattleCoreAccountSnapshot
{
    /**
     * Creates a snapshot whose dates retain the native month/day without inventing a year.
     *
     * @param  list<array{name: string, registered: bool|null, month: int, day: int}>  $registrations
     */
    public function __construct(
        public bool $available,
        public array $registrations,
        public int $unclaimedRewardItems,
    ) {}
}
