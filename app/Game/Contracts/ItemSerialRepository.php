<?php

namespace App\Game\Contracts;

/**
 * Reserves unique item serials from the game server's authoritative counter.
 */
interface ItemSerialRepository
{
    /**
     * Advances the game counter and returns the newly reserved serial.
     */
    public function nextSerial(): int;
}
