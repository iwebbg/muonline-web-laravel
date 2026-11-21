<?php

namespace App\Game\Contracts;

/**
 * Supplies the active adapter's location names to shared movement previews.
 */
interface MapNameLookup
{
    /**
     * Resolves a location label without granting movement to that location.
     */
    public function nameFor(int $mapNumber): string;
}
