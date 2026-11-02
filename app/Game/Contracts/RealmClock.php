<?php

namespace App\Game\Contracts;

use DateTimeImmutable;

/**
 * Provides the authoritative current instant used by realm schedules.
 */
interface RealmClock
{
    /**
     * Returns the current instant from the active realm data source.
     */
    public function now(): DateTimeImmutable;
}
