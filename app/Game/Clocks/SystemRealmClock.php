<?php

namespace App\Game\Clocks;

use App\Game\Contracts\RealmClock;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Uses the configured local timezone while the application runs on fixtures.
 */
final class SystemRealmClock implements RealmClock
{
    /**
     * Returns the current fixture instant in the configured realm timezone.
     */
    public function now(): DateTimeImmutable
    {
        $timezone = new DateTimeZone((string) config('game.event_timezone'));

        return CarbonImmutable::now($timezone)->toDateTimeImmutable();
    }
}
