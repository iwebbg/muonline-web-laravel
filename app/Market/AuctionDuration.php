<?php

namespace App\Market;

/**
 * Defines the player-selectable lifetime of one timed auction.
 */
enum AuctionDuration: string
{
    case OneHour = '1';
    case SixHours = '6';
    case TwelveHours = '12';
    case OneDay = '24';
    case ThreeDays = '72';
    case SevenDays = '168';

    /**
     * Returns the duration as an integer hour count for expiry calculations.
     */
    public function hours(): int
    {
        return (int) $this->value;
    }

    /**
     * Returns the stable translation suffix used by player-facing selectors.
     */
    public function translationKey(): string
    {
        return match ($this) {
            self::OneHour => 'one_hour',
            self::SixHours => 'six_hours',
            self::TwelveHours => 'twelve_hours',
            self::OneDay => 'one_day',
            self::ThreeDays => 'three_days',
            self::SevenDays => 'seven_days',
        };
    }
}
