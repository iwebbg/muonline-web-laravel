<?php

namespace App\Game\Events;

/**
 * Distinguishes recurring, instanced and inactive standard game activities.
 */
enum EventActivityAvailability: string
{
    case Scheduled = 'scheduled';
    case OnDemand = 'on-demand';
    case InGame = 'in-game';
    case Disabled = 'disabled';
    case Closed = 'closed';
}
